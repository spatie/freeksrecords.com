<?php

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\RecordCollection;

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
    $response = $this->get('/people/shelfowner')->assertOk()
        ->assertSee('30 releases')->assertSee('Shelfowner’s collection')
        ->assertDontSee('name="format"', false)
        ->assertDontSee('Other owner album')->assertDontSee('Secret personal note')
        ->assertDontSee('Shared detail only')->assertDontSee('Injected payload secret')
        ->assertDontSee('Injected credential')->assertDontSee($owner->email)
        ->assertDontSee('</script><script>alert', false);
    preg_match('/<script id="profile-shelf-data" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $summaries = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    expect($summaries)->toHaveCount(30);
    $copy = $owner->records()->where('instance_id', 1)->firstOrFail();
    expect(collect($summaries)->firstWhere('instanceId', 1)['shareUrl'])->toBe('/shelves/'.$copy->id)
        ->not->toBe('/shelves/'.$overlap->id)
        ->and($summaries[0]['addedAt'])->toBe($record->added_at->toIso8601String())
        ->and($summaries[0])->not->toHaveKey('tracks')
        ->and($summaries[0]['trackTitles'])->toContain($record->payload['tracks'][0]['title']);
    $this->get(collect($summaries)->firstWhere('instanceId', 1)['shareUrl'])->assertOk()->assertSee('Shelf album 1');
    $owner->update(['visibility' => 'only_me']);
    $this->get('/people/shelfowner')->assertOk()->assertDontSee('profile-shelf-data')->assertDontSee('Trimmed album')->assertDontSee('Shelf album');
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
    $profile = $this->get('/people/owner')->assertOk()->assertSee('Owner')->assertSee('@owner');
    $response = $this->get('/shelves/'.$record->id);
    if ($allowed) {
        $profile->assertSee('Private Test Album')->assertSee('1 release');
        $response->assertOk()->assertSee('Private Test Album');
    } else {
        $profile->assertDontSee('Private Test Album')->assertDontSee('profile-shelf-data')
            ->assertDontSee('1 release')->assertDontSee($owner->email);
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
    $this->get('/friends')->assertSee('Accept')->assertSee('Decline');
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

it('prioritizes importing a first collection without empty feed and request clutter', function () {
    $viewer = socialUser('newcollector');
    $this->actingAs($viewer)->get('/feed')->assertOk()
        ->assertSee('Add your records')->assertSee('Connect Discogs')
        ->assertSee('action="/discogs/connect"', false)
        ->assertDontSee('Nothing new yet')->assertDontSee('0 releases')
        ->assertDontSee('Friend requests')->assertDontSee('View my collection')
        ->assertDontSee('Good records. Good company.')->assertDontSee('Back to Freek’s collection')
        ->assertDontSee('Historical first imports')->assertDontSee('The listening circle / Latest additions');

    $requester = socialUser('requester');
    Friendship::query()->create([
        'user_low' => min($viewer->id, $requester->id), 'user_high' => max($viewer->id, $requester->id),
        'requested_by' => $requester->id,
    ]);
    $this->get('/feed')->assertSeeInOrder(['Add your records', 'Friend requests', 'Requester'])
        ->assertSee('Accept')->assertSee('Decline');

    $viewer->update(['discogs_token' => 'test-token', 'sync_status' => 'Syncing']);
    $this->get('/feed')->assertOk()->assertSee('Syncing')->assertSee('View import status')
        ->assertDontSee('action="/discogs/connect"', false);
});

it('keeps the normal feed after importing even an empty collection', function (bool $hasRecords) {
    $viewer = socialUser('returningcollector');
    $hasRecords
        ? socialRecord($viewer)
        : $viewer->update(['collection_imported_at' => now()]);
    $this->actingAs($viewer)->get('/feed')->assertOk()->assertSee('Nothing new yet')
        ->assertSee('View my collection')->assertSee('Find friends')->assertDontSee('Add your records')
        ->assertDontSee('action="/discogs/connect"', false)->assertDontSee('Friend requests');
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
    $this->get('/shelves/'.$record->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
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
    SocialNotification::send($viewer, $owner, 'comment', $record);
    SocialNotification::send($owner, $viewer, 'friend_accepted');
    $this->actingAs($viewer)->get('/notifications')->assertSee('Visible notification album')->assertDontSee('accepted your friend request');
    $owner->update(['visibility' => 'only_me']);
    $this->get('/notifications')->assertDontSee('Visible notification album')->assertDontSee('left a sleeve note');
    $this->post('/notifications/read')->assertRedirect();
    expect(SocialNotification::query()->whereNotNull('read_at')->count())->toBe(0);
    $owner->update(['visibility' => 'friends']);
    $this->post('/notifications/read');
    expect(SocialNotification::query()->where('user_id', $viewer->id)->first()->read_at)->not->toBeNull()
        ->and(SocialNotification::query()->where('user_id', $owner->id)->first()->read_at)->toBeNull();
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
