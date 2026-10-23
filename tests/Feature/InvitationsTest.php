<?php

use App\Models\Friendship;
use App\Models\Invitation;
use App\Notifications\FriendRequest;
use App\Notifications\InvitationMail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
});

function invitationToken(): string
{
    return Notification::sent(new AnonymousNotifiable, InvitationMail::class)->last()->token;
}

it('requires a verified sender and rejects invalid and self addresses', function () {
    $this->get('/friends/invite')->assertRedirect('/login');
    $this->post('/friends/invite', ['email' => 'friend@example.test'])->assertRedirect('/login');
    $sender = socialUser('sender');
    $sender->update(['email_verified_at' => null]);
    $this->actingAs($sender)->post('/friends/invite', ['email' => 'friend@example.test'])->assertRedirect('/verify-email');
    $sender->update(['email_verified_at' => now()]);
    $this->post('/friends/invite', ['email' => 'invalid'])->assertSessionHasErrors('email');
    $this->post('/friends/invite', ['email' => ' SENDER@example.test '])->assertSessionHasErrors('email');
    $this->assertDatabaseCount('invitations', 0);
    Notification::assertNothingSent();
});

it('creates only a pending request for a known verified recipient and deduplicates', function () {
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $this->actingAs($sender)->post('/friends/invite', ['email' => ' RECIPIENT@example.test '])->assertRedirect()->assertSessionHasNoErrors();
    $status = session('status');
    $this->post('/friends/invite', ['email' => $recipient->email])->assertSessionHas('status', $status);
    $this->assertDatabaseCount('friendships', 1);
    expect(Friendship::query()->first()->accepted_at)->toBeNull();
    Notification::assertSentToTimes($recipient, FriendRequest::class, 1);
    Notification::assertNothingSentTo($sender);
    Notification::assertSentOnDemandTimes(InvitationMail::class, 0);
    $this->post('/friends/invite', ['email' => 'new@example.test'])->assertSessionHas('status', $status);
});

it('emails unknown recipients with a hashed expiring token and no request on GET', function () {
    $sender = socialUser('sender');
    $this->actingAs($sender)->post('/friends/invite', ['email' => ' New@example.test '])->assertSessionHasNoErrors();
    $token = invitationToken();
    $invitation = Invitation::query()->firstOrFail();
    expect(strlen($token))->toBe(64);
    expect($invitation->token_hash)->toBe(hash('sha256', $token));
    expect($invitation->email)->toBe('new@example.test');
    expect($invitation->expires_at->diffInSeconds(now()->addDays(7)))->toBeLessThan(2);
    $mail = Notification::sent(new AnonymousNotifiable, InvitationMail::class)->first();
    $html = (string) $mail->toMail(new stdClass)->render();
    expect($html)->toContain('See your invitation', '/invitations/'.$token, 'text-decoration: none');
    $this->post('/logout');
    $url = route('invitations.show', $token);
    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertSessionHas('url.intended', $url)
        ->assertInertia(fn (Assert $page) => $page->component('social/InvitationPage')->where('sender.handle', 'sender')->where('friendship', null)->missing('email'));
    $this->assertDatabaseCount('friendships', 0);
    expect($invitation->fresh()->fulfilled_at)->toBeNull();
    $this->get('/invitations/'.str_repeat('a', 64))->assertNotFound();
    $this->travel(8)->days();
    $this->get($url)->assertNotFound();
});

it('fulfills only matching verified emails once and retains the landing until expiry', function () {
    $sender = socialUser('sender', 'only_me');
    $recipient = socialUser('recipient');
    $recipient->update(['email_verified_at' => null]);
    $this->actingAs($sender)->post('/friends/invite', ['email' => $recipient->email]);
    $token = invitationToken();
    $stranger = socialUser('stranger');
    event(new Verified($stranger));
    event(new Verified($recipient));
    $this->assertDatabaseCount('friendships', 0);
    $this->actingAs($stranger)->get('/invitations/'.$token)->assertInertia(fn (Assert $page) => $page->where('matches', false)->where('friendship', null));
    $recipient->update(['email_verified_at' => now()]);
    event(new Verified($recipient));
    event(new Verified($recipient));
    $this->assertDatabaseCount('friendships', 1);
    Notification::assertSentToTimes($recipient, FriendRequest::class, 1);
    expect(Invitation::query()->first()->fulfilled_at)->not->toBeNull();
    $this->flushSession();
    $this->actingAs($recipient)->get('/invitations/'.$token)->assertInertia(fn (Assert $page) => $page->where('matches', true)->where('friendship.requested_by', $sender->id)->where('friendship.accepted_at', null));
    $this->from('/invitations/'.$token)->post('/friends/'.$sender->public_id, ['action' => 'accept'])->assertRedirect('/invitations/'.$token)->assertSessionHasNoErrors();
    expect(Friendship::query()->first()->accepted_at)->not->toBeNull();
    $this->actingAs($recipient);
    $this->get('/invitations/'.$token)->assertInertia(fn (Assert $page) => $page->where('friendship.accepted_at', fn ($value) => $value !== null));
    expect($sender->fresh()->visibility)->toBe('only_me');
    expect($sender->canViewCollection($recipient))->toBeFalse();
    Friendship::query()->delete();
    event(new Verified($recipient));
    $this->get('/invitations/'.$token)->assertOk();
    $this->assertDatabaseCount('friendships', 0);
});

it('preserves existing accepted and reverse pending pairs during verification', function (bool $accepted) {
    $sender = socialUser('sender');
    $recipient = socialUser('recipient');
    $recipient->update(['email_verified_at' => null]);
    $this->actingAs($sender)->post('/friends/invite', ['email' => $recipient->email]);
    $connection = socialFriends($recipient, $sender);
    $connection->update(['accepted_at' => $accepted ? now() : null]);
    $recipient->update(['email_verified_at' => now()]);
    event(new Verified($recipient));
    expect($connection->fresh()->requested_by)->toBe($recipient->id);
    expect($connection->fresh()->accepted_at !== null)->toBe($accepted);
    Notification::assertNotSentTo($recipient, FriendRequest::class);
})->with([true, false]);

it('ignores expired invitations and cascades deletion of the inviter', function () {
    $sender = socialUser('sender');
    $this->actingAs($sender)->post('/friends/invite', ['email' => 'recipient@example.test']);
    $token = invitationToken();
    Invitation::query()->update(['expires_at' => now()->subSecond()]);
    event(new Verified(socialUser('recipient')));
    $this->assertDatabaseCount('friendships', 0);
    $sender->delete();
    $this->assertDatabaseCount('invitations', 0);
    $this->get('/invitations/'.$token)->assertNotFound();
});

it('enforces daily resend and sender limits and invalidates superseded tokens', function () {
    $sender = socialUser('sender');
    $this->actingAs($sender)->post('/friends/invite', ['email' => 'recipient@example.test']);
    $token = invitationToken();
    $this->post('/friends/invite', ['email' => 'recipient@example.test']);
    Notification::assertSentOnDemandTimes(InvitationMail::class, 1);
    $this->travel(25)->hours();
    $this->post('/friends/invite', ['email' => 'recipient@example.test']);
    Notification::assertSentOnDemandTimes(InvitationMail::class, 2);
    $this->get('/invitations/'.$token)->assertNotFound();
    for ($attempt = 0; $attempt < 20; $attempt++) {
        RateLimiter::hit('invitations:'.$sender->id, 86400);
    }
    $this->post('/friends/invite', ['email' => 'another@example.test'])->assertSessionHasErrors('email');
    $this->assertDatabaseCount('invitations', 1);
});
