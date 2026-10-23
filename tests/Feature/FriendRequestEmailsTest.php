<?php

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Notifications\FriendRequest;
use App\Notifications\FriendRequestAccepted;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

it('emails one verified recipient per new friend request with signed accept and decline links', function () {
    Notification::fake();
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $sender->update(['name' => '<script>Not markup</script>']);
    $this->actingAs($sender)->post('/friends/'.$recipient->public_id, ['action' => 'request'])->assertRedirect()->assertSessionMissing('status');
    $this->post('/friends/'.$recipient->public_id, ['action' => 'request'])->assertRedirect();
    Notification::assertSentToTimes($recipient, FriendRequest::class, 1);
    Notification::assertNotSentTo($sender, FriendRequest::class);
    Notification::assertSentTo($recipient, FriendRequest::class, function (FriendRequest $notification) use ($recipient) {
        $message = $notification->toMail($recipient);
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());
        expect($html)->toContain('Accept friend request', 'Decline friend request', 'class="inner-body"', '&lt;script&gt;Not markup&lt;/script&gt;')
            ->not->toContain('<script>Not markup</script>');
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $acceptButton = (new DOMXPath($document))->query('//a[@class="request-accept"]')->item(0);
        expect($acceptButton?->attributes?->getNamedItem('style')?->nodeValue)->toContain('text-decoration: none');
        expect($text)->toContain('Only me stay private', 'expire in seven days')
            ->not->toContain('<table', '<style');
        foreach ($message->viewData['links'] as $action => $url) {
            expect($url)->toContain('/'.$recipient->public_id.'/'.$action.'?', 'signature=');
            expect(html_entity_decode($html))->toContain('href="'.$url.'"');
            expect($text)->toContain(ucfirst($action).' friend request: '.$url);
        }

        return true;
    });
});

it('confirms email responses without signing in, never mutates on GET and consumes both choices', function (string $action) {
    Notification::fake();
    $sender = socialUser('sender', 'friends');
    $recipient = socialUser('recipient', 'only_me');
    socialRecord($sender);
    $this->actingAs($sender)->post('/friends/'.$recipient->public_id, ['action' => 'request']);
    $pending = Friendship::query()->firstOrFail();
    $links = (new FriendRequest($pending->id))->toMail($recipient)->viewData['links'];
    $this->post('/logout');

    foreach ($links as $url) {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('social/FriendRequestResponsePage')->where('sender', $sender->handle));
        expect($pending->fresh()->accepted_at)->toBeNull();
    }
    $this->post($links[$action])->assertRedirect($links[$action]);
    $this->assertGuest();
    expect($recipient->fresh()->visibility)->toBe('only_me');
    if ($action === 'accept') {
        expect($pending->fresh()->accepted_at)->not->toBeNull();
        $this->assertDatabaseCount('social_notifications', 2);
        Notification::assertSentToTimes($sender, FriendRequestAccepted::class, 1);
        Notification::assertNotSentTo($recipient, FriendRequestAccepted::class);
    } else {
        expect($pending->fresh())->toBeNull();
        $this->assertDatabaseCount('social_notifications', 1);
        Notification::assertNothingSentTo($sender);
    }
    foreach ($links as $url) {
        $this->post($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('sender', null));
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('sender', null));
    }
    expect(Friendship::query()->whereNotNull('accepted_at')->count())->toBe($action === 'accept' ? 1 : 0);
    $this->get('/shelves/'.$sender->records()->first()->public_id)->assertRedirect('/login');
    $responses = Activity::query()->whereIn('event', ['friend_request_accepted', 'friend_request_declined'])->get();
    expect($responses)->toHaveCount(1)
        ->and($responses->first()->causer_id)->toBe($recipient->id)
        ->and($responses->first()->subject_id)->toBe($sender->id);
})->with(['accept', 'decline']);

it('emails the requester once with a collection link only when the accepted friends collection is visible', function (bool $requesterCreatedFirst, string $visibility) {
    Notification::fake();
    $first = socialUser('firstcollector');
    $second = socialUser('secondcollector');
    $requester = $requesterCreatedFirst ? $first : $second;
    $friend = $requesterCreatedFirst ? $second : $first;
    $friend->update(['name' => '<em>Robin & friends</em>', 'visibility' => $visibility]);

    $this->actingAs($requester)->post('/friends/'.$friend->public_id, ['action' => 'request'])->assertRedirect();
    Notification::assertNotSentTo($requester, FriendRequestAccepted::class);
    $this->post('/logout');
    $this->actingAs($friend)->post('/friends/'.$requester->public_id, ['action' => 'accept'])->assertRedirect();
    $this->post('/friends/'.$requester->public_id, ['action' => 'accept'])->assertForbidden();
    Notification::assertSentToTimes($requester, FriendRequestAccepted::class, 1);
    Notification::assertNotSentTo($friend, FriendRequestAccepted::class);
    Notification::assertSentTo($requester, FriendRequestAccepted::class, function (FriendRequestAccepted $notification) use ($requester, $friend, $visibility) {
        $message = $notification->toMail($requester);
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());
        $url = route('profile', $friend->handle);

        expect($html)->toContain('&lt;em&gt;Robin &amp; friends&lt;/em&gt;', 'accepted your friend request')
            ->not->toContain('<em>Robin & friends</em>');
        expect($text)->not->toContain('<table', '<style');
        if ($visibility === 'only_me') {
            expect($html)->not->toContain('See their collection', $url);
            expect($text)->not->toContain('See their collection', $url);
        } else {
            expect($html)->toContain('href="'.$url.'"');
            expect($text)->toContain('See their collection: '.$url);
        }

        return true;
    });
})->with([[true, 'only_me'], [false, 'friends'], [true, 'everyone']]);

it('suppresses queued acceptance emails for unverified requesters or friendships that no longer exist', function () {
    $requester = socialUser('requester');
    $friend = socialUser('friend');
    $stranger = socialUser('stranger');
    $friendship = socialFriends($requester, $friend);
    $notification = new FriendRequestAccepted($friendship->id);

    expect($notification->shouldSend($requester, 'mail'))->toBeTrue()
        ->and($notification->shouldSend($friend, 'mail'))->toBeFalse()
        ->and($notification->shouldSend($stranger, 'mail'))->toBeFalse();
    $requester->update(['email_verified_at' => null]);
    expect($notification->shouldSend($requester, 'mail'))->toBeFalse();
    $requester->update(['email_verified_at' => now()]);
    $friendship->update(['accepted_at' => null]);
    expect($notification->shouldSend($requester, 'mail'))->toBeFalse();
    $friendship->delete();
    socialFriends($requester, $friend);
    expect($notification->shouldSend($requester, 'mail'))->toBeFalse();
});

it('rejects tampered and expired links and never applies an old email to a replacement request', function () {
    Notification::fake();
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $stranger = socialUser('stranger');
    $this->actingAs($sender)->post('/friends/'.$recipient->public_id, ['action' => 'request']);
    $pending = Friendship::query()->firstOrFail();
    $url = (new FriendRequest($pending->id))->toMail($recipient)->viewData['links']['accept'];
    $this->post('/logout');
    foreach ([str_replace('/accept?', '/decline?', $url), str_replace('/'.$recipient->public_id.'/accept?', '/'.$stranger->public_id.'/accept?', $url), strtok($url, '?')] as $tampered) {
        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();
    }
    $this->travel(7)->days();
    $this->travel(1)->seconds();
    $this->get($url)->assertForbidden();
    $this->post($url)->assertForbidden();
    $this->travelBack();
    expect($pending->fresh()->accepted_at)->toBeNull();
    $this->actingAs($sender)->post('/friends/'.$recipient->public_id, ['action' => 'cancel']);
    $this->post('/friends/'.$recipient->public_id, ['action' => 'request']);
    $this->post('/logout');
    $this->post($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('sender', null));
    expect(Friendship::query()->firstOrFail()->accepted_at)->toBeNull();
});

it('offers notification actions only for current incoming requests and removes them after either response', function (string $action) {
    Notification::fake();
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $this->actingAs($sender)->post('/friends/'.$recipient->public_id, ['action' => 'request']);
    $this->post('/logout');
    $this->actingAs($recipient)->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertJsonPath('notifications.data.0.canRespond', true);
    $this->post('/friends/'.$sender->public_id, ['action' => $action])->assertRedirect();
    $this->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertJsonPath('notifications.data.0.canRespond', false);
    expect(SocialNotification::query()->where('user_id', $recipient->id)->count())->toBe(1);
})->with(['accept', 'decline']);
