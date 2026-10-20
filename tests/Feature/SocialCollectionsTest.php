<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\RecordCollection;
use Inertia\Testing\AssertableInertia as Assert;

it('renders only the current authorized feed page with distinct mixed-owner copies and safe attribution', function () {
    $viewer = socialUser('feedviewer');
    $first = socialUser('firstfriend', 'friends');
    $second = socialUser('secondfriend', 'everyone');
    socialFriends($viewer, $first);
    socialFriends($viewer, $second);
    foreach (range(1, 24) as $instance) {
        socialRecord($first, 'Earlier record '.$instance, date: '2026-10-01 12:00:00', instance: $instance);
    }
    $older = socialRecord($first, 'Older shared copy', date: '2026-10-15 12:00:00', instance: 500);
    $newer = socialRecord($second, 'Newer shared copy', date: '2026-10-16 12:00:00', instance: 500);
    $second->update(['discogs_token' => 'owner-token-secret']);
    $newer->update([
        'private_note' => 'Private feed note', 'shared_note' => 'Detail-only feed note',
        'payload' => [...$newer->payload, 'oauth_token' => 'Payload token secret', 'private_note' => 'Payload note secret',
            'title' => '</script><script>alert("feed")</script>'],
    ]);
    $hidden = socialUser('hiddenfriend', 'only_me');
    socialFriends($viewer, $hidden);
    socialRecord($hidden, 'Hidden feed record');
    socialRecord($second, 'Initial feed import', activity: false, instance: 501);

    $response = $this->actingAs($viewer)->get('/feed')->assertOk()
        ->assertSeeInOrder(['Newer shared copy', 'Older shared copy'])
        ->assertDontSee('Hidden feed record')->assertDontSee('Initial feed import')
        ->assertDontSee('record-grid')->assertDontSee('record-card')
        ->assertDontSee('Private feed note')->assertDontSee('Detail-only feed note')
        ->assertDontSee('Payload token secret')->assertDontSee('Payload note secret')
        ->assertDontSee('owner-token-secret')->assertDontSee($second->email)
        ->assertDontSee('</script><script>alert', false);
    $summaries = $response->inertiaProps('records.data');
    expect($summaries)->toHaveCount(24)
        ->and(array_column(array_slice($summaries, 0, 2), 'instanceId'))->toBe([500, 500])
        ->and(array_column(array_slice($summaries, 0, 2), 'shareUrl'))->toBe(['/shelves/'.$newer->id, '/shelves/'.$older->id])
        ->and($summaries[0]['owner'])->toBe(['name' => $second->name, 'profileUrl' => '/secondfriend'])
        ->and($summaries[1]['owner'])->toBe(['name' => $first->name, 'profileUrl' => '/firstfriend'])
        ->and($summaries[0]['addedAt'])->toBe($newer->added_at->toIso8601String())
        ->and($summaries[0])->not->toHaveKey('tracks');
    $this->get($summaries[0]['shareUrl'])->assertOk()->assertSee('Newer shared copy');
    $this->get($summaries[1]['shareUrl'])->assertOk()->assertSee('Older shared copy');
    $this->get('/feed?page=2')->assertOk()->assertDontSee('Newer shared copy')->assertDontSee('Older shared copy')
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 2));
});

it('loads the whole visible shelf with owner-specific social links and only safe summary fields', function () {
    $owner = socialUser('shelfowner', 'everyone');
    $other = socialUser('othershelf', 'everyone');
    $overlap = socialRecord($other, 'Other owner album', instance: 1);
    foreach (range(1, 30) as $instance) {
        $record = socialRecord($owner, 'Shelf album '.$instance, instance: $instance);
    }
    $record->update(['private_note' => 'Secret personal note', 'shared_note' => 'Shared detail only', 'payload' => [
        ...$record->payload,
        'title' => '</script><script>alert("shelf")</script>', 'displayTitle' => 'Trimmed album',
        'artist' => 'Trimmed artist', 'artists' => ['Trimmed artist'],
        'private_note' => 'Injected payload secret', 'oauth_token' => 'Injected credential',
    ]]);
    $response = $this->get(route('profile', $owner->handle))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('records', 30)->where('person.name', 'Shelfowner'))
        ->assertDontSee('name="format"', false)
        ->assertDontSee('Other owner album')->assertDontSee('Secret personal note')
        ->assertDontSee('Shared detail only')->assertDontSee('Injected payload secret')
        ->assertDontSee('Injected credential')->assertDontSee($owner->email)
        ->assertDontSee('</script><script>alert', false);
    $summaries = $response->inertiaProps('records');
    expect($summaries)->toHaveCount(30);
    $copy = $owner->records()->where('instance_id', 1)->firstOrFail();
    expect(collect($summaries)->firstWhere('instanceId', 1)['shareUrl'])->toBe('/shelves/'.$copy->id)
        ->not->toBe('/shelves/'.$overlap->id)
        ->and($summaries[0]['addedAt'])->toBe($record->added_at->toIso8601String())
        ->and($summaries[0])->not->toHaveKey('tracks')
        ->and($summaries[0]['trackTitles'])->toContain($record->payload['tracks'][0]['title']);
    $this->get(collect($summaries)->firstWhere('instanceId', 1)['shareUrl'])->assertOk()->assertSee('Shelf album 1');
    $owner->update(['visibility' => 'only_me']);
    $this->get(route('profile', $owner->handle))->assertOk()->assertDontSee('profile-shelf-data')->assertDontSee('Trimmed album')->assertDontSee('Shelf album');
});

it('uses root profile URLs while retaining the old profile alias without redirects', function () {
    $owner = socialUser('canonical_owner', 'everyone');
    socialRecord($owner, 'Canonical record');
    expect(route('profile', $owner->handle))->toBe(url('/canonical_owner'));
    foreach (['/canonical_owner', '/people/canonical_owner'] as $path) {
        $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component('social/ProfilePage'))->assertSee('Canonical record');
    }
    foreach (['/unknown_collector', '/ab', '/InvalidHandle', '/invalid-handle', '/'.str_repeat('a', 31)] as $path) {
        $this->get($path)->assertNotFound();
    }
    foreach (['/login', '/register', '/forgot-password'] as $path) {
        $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component('social/AuthPage'));
    }
    $this->get('/settings')->assertRedirect('/login');
    $this->get('/feed')->assertRedirect('/login');
});

it('preserves shelf browsing context in the record return link without allowing external destinations', function () {
    $owner = socialUser('browsingcollector', 'everyone');
    $record = socialRecord($owner);
    $browse = 'decade=1980s&q=Blue Notes';
    $this->get('/shelves/'.$record->id.'?'.http_build_query(['browse' => $browse]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('collectionUrl', url('/browsingcollector').'#'.$browse));
    $this->get('/shelves/'.$record->id.'?browse=https://example.com')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('collectionUrl', url('/browsingcollector').'#https://example.com'));
    $this->get('/shelves/'.$record->id.'?browse[]=invalid')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('collectionUrl', url('/browsingcollector')));
});

it('enforces the full collection visibility matrix on profiles and record pages', function (string $visibility, string $viewerKind, bool $allowed) {
    $owner = socialUser('owner', $visibility);
    $friend = socialUser('friend');
    $stranger = socialUser('stranger');
    socialFriends($owner, $friend);
    $record = socialRecord($owner);
    if ($viewerKind !== 'guest') {
        $this->actingAs(match ($viewerKind) {
            'owner' => $owner, 'friend' => $friend, default => $stranger
        });
    }
    $profile = $this->get(route('profile', $owner->handle))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('person.name', 'Owner')->where('person.handle', 'owner')->where('visible', $allowed));
    $response = $this->get('/shelves/'.$record->id);
    if ($allowed) {
        $profile->assertSee('Private Test Album')->assertInertia(fn (Assert $page) => $page->has('records', 1));
        $response->assertOk()->assertSee('Private Test Album');
    } else {
        $profile->assertDontSee('Private Test Album')->assertDontSee($owner->email)
            ->assertInertia(fn (Assert $page) => $page->where('records', null));
        $response->assertNotFound();
    }
    expect($profile->headers->get('Cache-Control'))->toContain('no-store', 'private');
})->with([
    ['only_me', 'guest', false], ['only_me', 'stranger', false], ['only_me', 'friend', false], ['only_me', 'owner', true],
    ['friends', 'guest', false], ['friends', 'stranger', false], ['friends', 'friend', true], ['friends', 'owner', true],
    ['everyone', 'guest', true], ['everyone', 'stranger', true], ['everyone', 'friend', true], ['everyone', 'owner', true],
]);

it('requires mutual acceptance, prevents self acceptance, and supports the complete request lifecycle', function () {
    $a = socialUser('alice', 'friends');
    $b = socialUser('bob', 'friends');
    $c = socialUser('carol');
    $record = socialRecord($b);
    $this->actingAs($a)->post('/friends/'.$a->id, ['action' => 'request'])->assertUnprocessable();
    $this->post('/friends/'.$b->id, ['action' => 'request'])->assertRedirect();
    $this->post('/friends/'.$b->id, ['action' => 'request'])->assertRedirect();
    $this->assertDatabaseCount('friendships', 1);
    $this->get('/shelves/'.$record->id)->assertNotFound();
    $this->post('/friends/'.$b->id, ['action' => 'accept'])->assertForbidden();
    $this->post('/logout');
    $this->actingAs($c)->post('/friends/'.$a->id, ['action' => 'accept'])->assertForbidden();
    $this->post('/logout');
    $this->actingAs($b)->post('/friends/'.$a->id, ['action' => 'request'])->assertRedirect();
    $this->get('/friends')->assertInertia(fn (Assert $page) => $page
        ->where('connections.0.accepted_at', null)->where('connections.0.requested_by', $a->id)->where('people.data.0.id', $a->id));
    $this->post('/friends/'.$a->id, ['action' => 'accept'])->assertRedirect();
    $this->post('/logout');
    $this->actingAs($a)->get('/shelves/'.$record->id)->assertOk();
    $this->get('/friends?q=bob')->assertSee('Bob')->assertDontSee('Carol');
    $this->post('/friends/'.$b->id, ['action' => 'unfriend'])->assertRedirect();
    $this->get('/shelves/'.$record->id)->assertNotFound();
    foreach (['cancel', 'decline'] as $action) {
        $this->post('/logout');
        $this->actingAs($a)->post('/friends/'.$b->id, ['action' => 'request']);
        $this->post('/logout');
        $this->actingAs($action === 'cancel' ? $a : $b)->post('/friends/'.($action === 'cancel' ? $b->id : $a->id), ['action' => $action])->assertRedirect();
        $this->assertDatabaseCount('friendships', 0);
    }
});

it('finds an exact username with the displayed at prefix and optional whitespace', function (string $input, string $handle) {
    $viewer = socialUser('search_viewer');
    $person = socialUser($handle);
    $this->actingAs($viewer)->get('/friends?'.http_build_query(['q' => $input]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('query', $handle)->has('people.data', 1)->where('people.data.0.id', $person->id));
})->with([
    ['bob', 'bob'], ['@BOB', 'bob'], ['  @Bob  ', 'bob'],
    ['@abcdefghijklmnopqrstuvwxyz1234', 'abcdefghijklmnopqrstuvwxyz1234'],
]);

it('prioritizes importing a first collection without empty feed and request clutter', function () {
    $viewer = socialUser('newcollector');
    $this->actingAs($viewer)->get('/feed')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('social/FeedPage')
            ->where('collectionCount', 0)->where('auth.user.discogsConnected', false)
            ->where('auth.user.importedAt', null)->has('records.data', 0)->has('incomingRequests', 0));
    foreach (['/settings', '/newcollector'] as $path) {
        $this->get($path)->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.collectionCount', 0)->where('auth.user.importedAt', null));
    }

    $requester = socialUser('requester');
    Friendship::query()->create([
        'user_low' => min($viewer->id, $requester->id), 'user_high' => max($viewer->id, $requester->id),
        'requested_by' => $requester->id,
    ]);
    $this->get('/feed')->assertInertia(fn (Assert $page) => $page
        ->where('collectionCount', 0)->has('incomingRequests', 1)->where('incomingRequests.0.id', $requester->id));

    $viewer->update(['discogs_token' => 'test-token', 'sync_status' => 'Syncing']);
    $this->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.syncStatus', 'Syncing')->where('auth.user.discogsConnected', true));
});

it('keeps the normal feed after importing even an empty collection', function (bool $hasRecords) {
    $viewer = socialUser('returningcollector');
    $hasRecords
        ? socialRecord($viewer)
        : $viewer->update(['collection_imported_at' => now()]);
    $this->actingAs($viewer)->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('collectionCount', $hasRecords ? 1 : 0)->where('auth.user.profileUrl', '/returningcollector')
        ->where('auth.user.importedAt', $viewer->collection_imported_at?->toIso8601String())
        ->has('records.data', 0)->has('incomingRequests', 0));
})->with([true, false]);

it('orders the feed by original addition date, excludes initial imports, and revokes activity dynamically', function () {
    $viewer = socialUser('viewer');
    $friend = socialUser('friend', 'friends');
    $stranger = socialUser('stranger', 'everyone');
    $connection = socialFriends($friend, $viewer);
    socialRecord($friend, 'Older addition', true, '2026-10-15 12:00:00', 1);
    socialRecord($friend, 'Newer addition', true, '2026-10-16 12:00:00', 2);
    socialRecord($friend, 'Initial import', false, '2026-10-17 12:00:00', 3);
    socialRecord($stranger, 'Not a friend');
    $this->actingAs($viewer)->get('/feed')->assertSeeInOrder(['Newer addition', 'Older addition'])->assertDontSee('Initial import')->assertDontSee('Not a friend');
    $friend->update(['visibility' => 'only_me']);
    $this->get('/feed')->assertDontSee('Newer addition');
    $friend->update(['visibility' => 'everyone']);
    $connection->delete();
    $this->get('/feed')->assertDontSee('Newer addition');
});

it('allows signed-in viewers to appreciate once, comment safely, and apply owner moderation', function () {
    $owner = socialUser('owner', 'everyone');
    $author = socialUser('author');
    $other = socialUser('other');
    $record = socialRecord($owner);
    $this->post('/shelves/'.$record->id.'/appreciations')->assertRedirect('/login');
    $this->actingAs($author)->post('/shelves/'.$record->id.'/appreciations')->assertRedirect();
    $this->post('/shelves/'.$record->id.'/appreciations')->assertRedirect();
    $this->assertDatabaseCount('appreciations', 1);
    $this->assertDatabaseCount('social_notifications', 1);
    $this->post('/shelves/'.$record->id.'/comments', ['body' => '<script>alert(1)</script>'])->assertRedirect();
    $comment = $record->comments()->firstOrFail();
    $this->get('/shelves/'.$record->id)->assertInertia(fn (Assert $page) => $page->where('comments.data.0.body', '<script>alert(1)</script>'))
        ->assertDontSee('<script>alert(1)</script>', false);
    $this->post('/shelves/'.$record->id.'/comments', ['body' => str_repeat('x', 2001)])->assertSessionHasErrors('body');
    $this->post('/logout');
    $this->actingAs($other)->delete('/shelves/'.$record->id.'/comments/'.$comment->id)->assertForbidden();
    $this->post('/logout');
    $another = socialRecord($owner, 'Another', instance: 124);
    $this->actingAs($owner)->delete('/shelves/'.$another->id.'/comments/'.$comment->id)->assertNotFound();
    $this->delete('/shelves/'.$record->id.'/comments/'.$comment->id)->assertRedirect();
    $owner->update(['comments_enabled' => false]);
    $this->post('/logout');
    $this->actingAs($author)->post('/shelves/'.$record->id.'/comments', ['body' => 'No'])->assertForbidden();
    $owner->update(['comments_enabled' => true]);
    $this->post('/shelves/'.$record->id.'/comments', ['body' => 'Remove me']);
    $comment = $record->comments()->firstOrFail();
    $owner->update(['visibility' => 'only_me']);
    $this->post('/shelves/'.$record->id.'/comments', ['body' => 'Denied'])->assertNotFound();
    $this->post('/shelves/'.$record->id.'/appreciations')->assertNotFound();
    $this->delete('/shelves/'.$record->id.'/comments/'.$comment->id)->assertRedirect();
    $this->assertDatabaseCount('comments', 0);
});

it('shows only the recipient’s currently visible notifications and scopes read updates', function () {
    $viewer = socialUser('viewer');
    $owner = socialUser('owner', 'friends');
    socialFriends($viewer, $owner);
    $record = socialRecord($owner, 'Visible notification album');
    $record->update(['payload' => [...$record->payload, 'artist' => 'Notification Artist'], 'private_note' => 'Never in a notification']);
    SocialNotification::send($viewer, $owner, 'comment', $record);
    SocialNotification::send($owner, $viewer, 'friend_accepted');
    $this->actingAs($viewer)->get('/notifications')->assertRedirect('/feed?notifications=1');
    $this->get('/settings')->assertInertia(fn (Assert $page) => $page->where('notificationCount', 1));
    $this->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertJsonCount(1, 'notifications.data')->assertJsonPath('notifications.data.0.url', '/shelves/'.$record->id.'#comments')
        ->assertJsonPath('notifications.data.0.text', 'commented on your record.')
        ->assertJsonPath('notifications.data.0.record.title', 'Visible notification album')
        ->assertJsonPath('notifications.data.0.record.artist', 'Notification Artist')
        ->assertJsonPath('notifications.data.0.actor.id', $owner->id)->assertJsonPath('unreadCount', 1)
        ->assertDontSee($owner->email)->assertDontSee('Never in a notification')->assertDontSee('accepted your friend request');
    $owner->update(['visibility' => 'only_me']);
    $this->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertDontSee('Visible notification album')->assertDontSee('commented on your record')
        ->assertJsonCount(0, 'notifications.data')->assertJsonPath('unreadCount', 0);
    $this->post('/notifications/read')->assertRedirect();
    expect(SocialNotification::query()->whereNotNull('read_at')->count())->toBe(0);
    $owner->update(['visibility' => 'friends']);
    $this->post('/notifications/read', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertNoContent();
    $this->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertJsonPath('notifications.data.0.url', '/shelves/'.$record->id.'#comments')
        ->assertJsonPath('notifications.data.0.unread', false)->assertJsonPath('unreadCount', 0);
    expect(SocialNotification::query()->where('user_id', $viewer->id)->first()->read_at)->not->toBeNull()
        ->and(SocialNotification::query()->where('user_id', $owner->id)->first()->read_at)->toBeNull();
});

it('keeps older notifications accessible in the popover', function () {
    $viewer = socialUser('viewer');
    $actor = socialUser('actor');
    SocialNotification::send($viewer, $actor, 'friend_accepted');
    foreach (range(1, 15) as $index) {
        SocialNotification::send($viewer, $actor, 'friend_request');
    }

    $this->actingAs($viewer)->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertSee('sent you a friend request')->assertDontSee('accepted your friend request')
        ->assertSee('page=2', false)->assertJsonPath('unreadCount', 16)->assertJsonCount(15, 'notifications.data');
    $this->get('/notifications?page=2', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertSee('accepted your friend request')->assertDontSee('sent you a friend request');
});

it('isolates legacy collection copies, snapshot fallback, and all legacy URLs from other owners', function () {
    $owner = socialUser('private');
    $record = socialRecord($owner, 'Never leak me', instance: 2189134863);
    $this->get('/collection')->assertOk()->assertDontSee('Never leak me');
    $this->getJson('/api/records/2189134863')->assertJsonPath('title', 'Shebang');
    $this->get('/record/2189134863/oren-ambarchi-shebang')->assertOk()->assertDontSee('Never leak me');
    $this->get('/record/2189134863/image.png')->assertRedirect();
    $this->get('/share/image.png')->assertRedirect();
    socialRecord($owner, 'Unknown legacy copy', instance: 999999);
    $this->getJson('/api/records/999999')->assertNotFound();
    User::freek()->update(['collection_imported_at' => now()]);
    app(RecordCollection::class)->flush();
    $this->getJson('/api/records/2189134863')->assertNotFound();
    expect($record->fresh())->not->toBeNull();
});

it('serves explicit safe people and viewer contracts on Inertia visits without serializing credentials', function () {
    $owner = socialUser('contract_owner', 'everyone');
    $viewer = socialUser('contract_viewer');
    foreach ([$owner, $viewer] as $person) {
        $person->update([
            'google_id' => 'google-subject-'.$person->id,
            'discogs_token' => 'discogs-access-'.$person->id,
            'discogs_secret' => 'discogs-secret-'.$person->id,
            'remember_token' => 'remember-secret-'.$person->id,
        ]);
    }
    socialFriends($owner, $viewer);
    $record = socialRecord($owner);
    $record->update([
        'private_note' => 'contract-private-note', 'shared_note' => 'contract-shared-note',
        'payload' => [...$record->payload, 'private_note' => 'injected-note', 'oauth_token' => 'injected-token',
            'tracks' => [[...$record->payload['tracks'][0], 'oauth_token' => 'injected-track-token']]],
    ]);
    $comment = $record->comments()->create(['user_id' => $viewer->id, 'body' => 'A public comment']);
    $headers = [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ];
    $personKeys = ['id', 'name', 'handle', 'profileUrl'];

    foreach (['/community', '/collections', '/contract_owner', '/feed', '/friends', '/settings', '/shelves/'.$record->id] as $path) {
        $response = $this->actingAs($viewer)->get($path, $headers)->assertOk()->assertHeader('X-Inertia', 'true');
        $response->assertJsonPath('props.auth.user.email', $viewer->email)
            ->assertJsonPath('props.auth.user.googleConnected', true)->assertJsonPath('props.auth.user.discogsConnected', true)
            ->assertDontSee($owner->email)->assertDontSee('contract-private-note')
            ->assertDontSee('google-subject-')->assertDontSee('discogs-access-')->assertDontSee('discogs-secret-')
            ->assertDontSee('remember-secret-')->assertDontSee($viewer->password)->assertDontSee($owner->password)
            ->assertDontSee('injected-note')->assertDontSee('injected-token')->assertDontSee('injected-track-token');
        expect(array_keys($response->json('props.auth.user')))->toBe([
            ...$personKeys, 'email', 'verified', 'visibility', 'legacyOwner', 'commentsEnabled', 'digestEnabled',
            'googleConnected', 'discogsConnected', 'discogsUsername', 'importedAt', 'lastSyncedAt', 'syncStatus', 'collectionCount',
        ])->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    }

    $profile = $this->get('/contract_owner', $headers)->assertJsonPath('props.visible', true);
    expect(array_keys($profile->json('props.person')))->toBe($personKeys);
    $release = $this->get('/shelves/'.$record->id, $headers)
        ->assertJsonMissingPath('props.record.privateNote')->assertJsonPath('props.record.canEditNotes', false)
        ->assertJsonPath('props.record.sharedNote', 'contract-shared-note')
        ->assertJsonPath('props.comments.data.0.id', $comment->id)->assertJsonPath('props.comments.data.0.canDelete', true);
    expect(array_keys($release->json('props.record.owner')))->toBe($personKeys)
        ->and(array_keys($release->json('props.comments.data.0')))->toBe(['id', 'body', 'user', 'canDelete'])
        ->and(array_keys($release->json('props.comments.data.0.user')))->toBe($personKeys)
        ->and(array_keys($release->json('props.comments')))->toBe(['data', 'prev_page_url', 'next_page_url']);

    $owner->update(['visibility' => 'only_me']);
    $this->get('/contract_owner', $headers)->assertJsonPath('props.visible', false)->assertJsonPath('props.records', null);
    $this->get('/shelves/'.$record->id, $headers)->assertNotFound();
    $this->actingAs($owner)->get('/shelves/'.$record->id, $headers)
        ->assertJsonPath('props.record.privateNote', 'contract-private-note')->assertJsonPath('props.record.canEditNotes', true)
        ->assertJsonPath('props.comments.data.0.canDelete', true);
});

it('redirects Inertia forms back with validation errors and refreshed props instead of JSON or empty responses', function () {
    $viewer = socialUser('forms_viewer');
    $record = socialRecord($viewer);
    $headers = [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ];
    $this->actingAs($viewer)->from('/settings')->patch('/settings', ['handle' => 'ab'], $headers)
        ->assertStatus(303)->assertRedirect('/settings')->assertSessionHasErrors(['handle', 'visibility']);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get('/settings', $headers)->assertJsonPath('component', 'social/SettingsPage')
        ->assertJsonStructure(['props' => ['errors' => ['handle', 'visibility']]])->assertJsonPath('props.auth.user.handle', 'forms_viewer');
    $this->from('/shelves/'.$record->id)->patch('/shelves/'.$record->id.'/notes', ['shared_note' => str_repeat('x', 5001)], $headers)
        ->assertStatus(303)->assertRedirect('/shelves/'.$record->id)->assertSessionHasErrors(['shared_note', 'private_note']);
    $this->from('/forms_viewer')->patch('/collection/visibility', ['visibility' => 'everyone'], $headers)
        ->assertStatus(303)->assertRedirect('/forms_viewer');
    $this->get('/forms_viewer', $headers)->assertJsonPath('props.auth.user.visibility', 'everyone');
    $actor = socialUser('forms_actor');
    SocialNotification::send($viewer, $actor, 'friend_request');
    $this->from('/feed')->post('/notifications/read', [], $headers)->assertRedirect('/feed');
    $this->get('/feed', $headers)->assertJsonPath('props.notificationCount', 0)
        ->assertJsonPath('props.flash.status', 'Notifications marked as read.');
});
