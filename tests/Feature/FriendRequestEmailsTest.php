<?php

use App\Models\Friendship;
use App\Models\SocialNotification;
use App\Notifications\FriendRequest;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('emails one verified recipient per new friend request with signed accept and decline links', function () {
    Notification::fake();
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $sender->update(['name' => '<script>Not markup</script>']);
    $this->actingAs($sender)->post('/friends/'.$recipient->id, ['action' => 'request'])->assertRedirect();
    $this->post('/friends/'.$recipient->id, ['action' => 'request'])->assertRedirect();
    Notification::assertSentToTimes($recipient, FriendRequest::class, 1);
    Notification::assertNotSentTo($sender, FriendRequest::class);
    Notification::assertSentTo($recipient, FriendRequest::class, function (FriendRequest $notification) use ($recipient) {
        $message = $notification->toMail($recipient);
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());
        expect($html)->toContain('Accept friend request', 'Decline friend request', 'class="inner-body"', '&lt;script&gt;Not markup&lt;/script&gt;')
            ->not->toContain('<script>Not markup</script>');
        expect($text)->toContain('Only me stay private', 'expire in seven days')
            ->not->toContain('<table', '<style');
        foreach ($message->viewData['links'] as $action => $url) {
            expect($url)->toContain('/'.$recipient->id.'/'.$action.'?', 'signature=');
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
    $this->actingAs($sender)->post('/friends/'.$recipient->id, ['action' => 'request']);
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
    } else {
        expect($pending->fresh())->toBeNull();
        $this->assertDatabaseCount('social_notifications', 1);
    }
    foreach ($links as $url) {
        $this->post($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('sender', null));
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('sender', null));
    }
    expect(Friendship::query()->whereNotNull('accepted_at')->count())->toBe($action === 'accept' ? 1 : 0);
    $this->get('/shelves/'.$sender->records()->first()->id)->assertNotFound();
})->with(['accept', 'decline']);

it('rejects tampered and expired links and never applies an old email to a replacement request', function () {
    Notification::fake();
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $stranger = socialUser('stranger');
    $this->actingAs($sender)->post('/friends/'.$recipient->id, ['action' => 'request']);
    $pending = Friendship::query()->firstOrFail();
    $url = (new FriendRequest($pending->id))->toMail($recipient)->viewData['links']['accept'];
    $this->post('/logout');
    foreach ([str_replace('/accept?', '/decline?', $url), str_replace('/'.$recipient->id.'/accept?', '/'.$stranger->id.'/accept?', $url), strtok($url, '?')] as $tampered) {
        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();
    }
    $this->travel(7)->days();
    $this->travel(1)->seconds();
    $this->get($url)->assertForbidden();
    $this->post($url)->assertForbidden();
    $this->travelBack();
    expect($pending->fresh()->accepted_at)->toBeNull();
    $this->actingAs($sender)->post('/friends/'.$recipient->id, ['action' => 'cancel']);
    $this->post('/friends/'.$recipient->id, ['action' => 'request']);
    $this->post('/logout');
    $this->post($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('sender', null));
    expect(Friendship::query()->firstOrFail()->accepted_at)->toBeNull();
});

it('offers notification actions only for current incoming requests and removes them after either response', function (string $action) {
    Notification::fake();
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $this->actingAs($sender)->post('/friends/'.$recipient->id, ['action' => 'request']);
    $this->post('/logout');
    $this->actingAs($recipient)->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertJsonPath('notifications.data.0.canRespond', true);
    $this->post('/friends/'.$sender->id, ['action' => $action])->assertRedirect();
    $this->get('/notifications', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertJsonPath('notifications.data.0.canRespond', false);
    expect(SocialNotification::query()->where('user_id', $recipient->id)->count())->toBe(1);
})->with(['accept', 'decline']);
