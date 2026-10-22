<?php

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Psr\Http\Message\RequestInterface;

beforeEach(function () {
    config(['services.google' => [
        'client_id' => 'test-google-client', 'client_secret' => 'test-google-secret',
        'redirect' => 'http://localhost/auth/google/callback',
    ]]);
    Notification::fake();
    $this->googleProfile = [
        'sub' => 'google-sub-123', 'email' => 'collector@example.test',
        'email_verified' => true, 'name' => 'A New Collector',
        'picture' => 'https://lh3.googleusercontent.com/test-collector-photo',
    ];
    $this->googleUnavailable = false;
    $client = new Client(['handler' => HandlerStack::create(function (RequestInterface $request) {
        $body = match ($request->getUri()->getPath()) {
            '/oauth2/v4/token' => ['access_token' => 'test-access-token-not-to-store', 'expires_in' => 3600],
            '/oauth2/v3/userinfo' => $this->googleProfile,
            default => throw new LogicException('Unexpected OAuth endpoint'),
        };

        return new FulfilledPromise(new Response($this->googleUnavailable ? 503 : 200, ['Content-Type' => 'application/json'], json_encode($body)));
    })]);
    Socialite::shouldReceive('driver')->with('google')->andReturnUsing(fn () => (new GoogleProvider(
        request(), config('services.google.client_id'), config('services.google.client_secret'), config('services.google.redirect'),
    ))->setHttpClient($client));
});

it('offers Google only when configured and starts a stateful PKCE flow with basic scopes', function () {
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->where('googleEnabled', true));
    $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->where('googleEnabled', true));
    $response = $this->get(route('google.redirect'))->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect(parse_url($response->headers->get('Location'), PHP_URL_HOST))->toBe('accounts.google.com')
        ->and($query['scope'])->toBe('openid profile email')
        ->and($query['redirect_uri'])->toBe('http://localhost/auth/google/callback')
        ->and($query['state'])->toBe(session('state'))->not->toBeEmpty()
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['code_challenge'])->toBe(rtrim(strtr(base64_encode(hash('sha256', session('code_verifier'), true)), '+/', '-_'), '='));

    config(['services.google.client_secret' => null]);
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->where('googleEnabled', false));
    $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->where('googleEnabled', false));
    $this->get(route('google.redirect'))->assertNotFound();
});

it('creates a private verified Google account and sends it straight to importing records without storing tokens', function () {
    $this->googleProfile['email'] = ' COLLECTOR@example.test ';
    $this->get(route('google.redirect'));
    $sessionId = session()->getId();
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))
        ->assertRedirect('/')->assertSessionMissing('google_pending')->assertSessionMissing('state')->assertSessionMissing('code_verifier');

    $user = User::query()->where('google_id', 'google-sub-123')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->email)->toBe('collector@example.test')->and($user->handle)->toBe('collector')
        ->and($user->name)->toBe('A New Collector')->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->visibility)->toBe('only_me')->and($user->digest_enabled)->toBeTrue()
        ->and($user->legacy_owner)->toBeFalse()->and($user->collection_imported_at)->toBeNull()
        ->and($user->google_avatar_url)->toBe($this->googleProfile['picture'])
        ->and(session()->getId())->not->toBe($sessionId)
        ->and($user->toArray())->not->toHaveKey('google_id')
        ->and($user->toArray())->not->toHaveKey('google_avatar_url')
        ->and(json_encode($user->getAttributes()))->not->toContain('test-access-token-not-to-store');
    $this->assertDatabaseCount('users', 2);
    Notification::assertNothingSent();
    $this->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page->where('collectionCount', 0)
        ->where('auth.user.importedAt', null)->where('auth.user.discogsConnected', false)->where('auth.user.googleConnected', true)
        ->where('auth.user.avatarUrl', $this->googleProfile['picture']));
});

it('lets a deleted legacy owner start fresh through Google without restoring the old collection', function () {
    $previous = User::freek();
    socialRecord($previous);
    $this->googleProfile['email'] = $previous->email;
    $previous->delete();

    $this->get(route('google.redirect'));
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))->assertRedirect('/');

    $user = User::query()->where('email', $previous->email)->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->id)->not->toBe($previous->id)
        ->and($user->legacy_owner)->toBeFalse()
        ->and($user->visibility)->toBe('only_me')
        ->and($user->collection_imported_at)->toBeNull()
        ->and($user->discogs_token)->toBeNull();
    $this->assertDatabaseCount('collection_records', 0);
    $this->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page->where('collectionCount', 0));
    $this->get('/collection')->assertNotFound();
});

it('signs in by Google subject without changing profile email or confusing another email-matched account', function () {
    $linked = socialUser('linked');
    $linked->update(['google_id' => 'google-sub-123', 'google_avatar_url' => 'https://lh3.googleusercontent.com/old-photo']);
    $other = socialUser('other');
    $this->googleProfile['email'] = $other->email;
    $this->get(route('google.redirect'));
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))->assertRedirect('/');
    $this->get('/')->assertRedirect('/linked');
    $this->assertAuthenticatedAs($linked);
    expect($linked->fresh()->email)->toBe('linked@example.test')->and($linked->fresh()->name)->toBe('Linked')
        ->and($linked->fresh()->google_avatar_url)->toBe($this->googleProfile['picture'])
        ->and($other->fresh()->google_id)->toBeNull();
    $this->assertDatabaseCount('users', 3);
});

it('falls back to initials when Google removes a photo or supplies an unsafe image URL', function (?string $picture) {
    $linked = socialUser('linked');
    $linked->update(['google_id' => 'google-sub-123', 'google_avatar_url' => 'https://lh3.googleusercontent.com/old-photo']);
    $this->googleProfile['picture'] = $picture;
    $this->get(route('google.redirect'));
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))->assertRedirect('/');
    $this->assertAuthenticatedAs($linked);
    expect($linked->fresh()->google_avatar_url)->toBeNull();
    $this->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.user.avatarUrl', null));
})->with([null, 'http://example.test/photo.jpg', 'javascript:alert(1)']);

it('never claims an existing password account or Freeks reserved ownership by email match', function (bool $legacy) {
    $owner = $legacy ? User::freek() : socialUser('existing');
    $original = $owner->fresh()->getAttributes();
    $this->googleProfile['email'] = strtoupper($owner->email);
    $this->get(route('google.redirect'));
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))
        ->assertRedirect('/login')->assertSessionHasErrors('google');
    $this->assertGuest();
    expect($owner->fresh()->getAttributes())->toBe($original);
})->with([false, true]);

it('rejects unverified or incomplete Google identities without creating an account', function (array $changes) {
    $this->googleProfile = [...$this->googleProfile, ...$changes];
    $this->get(route('google.redirect'));
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))
        ->assertRedirect('/login')->assertSessionHasErrors('google');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
})->with([
    [['email_verified' => false]], [['email_verified' => 'true']], [['email' => null]],
    [['email' => 'invalid']], [['sub' => null]], [['sub' => str_repeat('x', 256)]],
]);

it('fails closed for wrong, missing, expired and cancelled OAuth flows and consumes pending credentials', function (string $failure) {
    if ($failure !== 'missing') {
        $this->get(route('google.redirect'));
    }
    $query = ['state' => session('state'), 'code' => 'valid-code'];
    if ($failure === 'state') {
        $query['state'] = 'untrusted-state';
    }
    if ($failure === 'expired') {
        $this->travel(11)->minutes();
    }
    if ($failure === 'cancelled') {
        $query['error'] = 'access_denied';
    }
    if ($failure === 'unavailable') {
        $this->googleUnavailable = true;
    }
    $this->get(route('google.callback', $query))->assertRedirect('/login')->assertSessionHasErrors('google')
        ->assertSessionMissing('google_pending')->assertSessionMissing('state')->assertSessionMissing('code_verifier');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
})->with(['state', 'missing', 'expired', 'cancelled', 'unavailable']);

it('rejects callback replay without creating or switching accounts', function () {
    $this->get(route('google.redirect'));
    $query = ['state' => session('state'), 'code' => 'valid-code'];
    $this->get(route('google.callback', $query))->assertRedirect('/');
    $user = User::query()->where('google_id', 'google-sub-123')->firstOrFail();
    $this->get(route('google.callback', $query))->assertRedirect('/settings')->assertSessionHasErrors('google');
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 2);
});

it('requires a verified session and the current password before linking without flashing that password', function () {
    $this->post(route('google.link'), ['current_password' => 'a-long-demo-password'])->assertRedirect('/login');
    $owner = socialUser('existing');
    $owner->update(['email_verified_at' => null]);
    $this->actingAs($owner)->post(route('google.link'), ['current_password' => 'a-long-demo-password'])->assertRedirect('/verify-email');
    $owner->update(['email_verified_at' => now()]);
    $this->actingAs($owner)->from('/settings')->post(route('google.link'), ['current_password' => 'wrong-password'])
        ->assertRedirect('/settings')->assertSessionHasErrors('current_password')
        ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('google_pending');
    expect($owner->fresh()->google_id)->toBeNull();
});

it('links only the password-confirmed account, preserves its collection and preferences, then allows Google sign-in', function () {
    $owner = socialUser('existing', 'friends');
    $record = socialRecord($owner);
    $other = socialUser('other');
    $original = $owner->fresh()->only(['password', 'email', 'name', 'handle', 'visibility', 'legacy_owner', 'digest_enabled']);
    $this->googleProfile['email'] = $owner->email;
    $this->actingAs($owner)->get('/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('social/SettingsPage')->where('auth.user.googleConnected', false));
    $this->post(route('google.link'), ['current_password' => 'a-long-demo-password', 'user_id' => $other->id, 'visibility' => 'everyone'])->assertRedirect();
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))->assertRedirect('/settings')->assertSessionHasNoErrors();
    expect($owner->fresh()->google_id)->toBe('google-sub-123')->and($owner->fresh()->only(array_keys($original)))->toEqual($original)
        ->and($owner->fresh()->google_avatar_url)->toBe($this->googleProfile['picture'])
        ->and($other->fresh()->google_id)->toBeNull()->and($record->fresh()->user_id)->toBe($owner->id);
    $this->actingAs($owner->fresh())->get('/settings')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.user.googleConnected', true));
    $this->post('/logout');
    $this->get(route('google.redirect'));
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'another-valid-code']))->assertRedirect('/');
    $this->assertAuthenticatedAs($owner);
    $this->assertDatabaseCount('users', 3);
    Notification::assertNothingSent();
});

it('refuses a different Google email or a subject already attached to another account', function (string $conflict) {
    $owner = socialUser('existing');
    $this->googleProfile['email'] = $conflict === 'email' ? 'different@example.test' : $owner->email;
    if ($conflict === 'subject') {
        socialUser('other')->update(['google_id' => 'google-sub-123']);
    }
    $this->actingAs($owner)->post(route('google.link'), ['current_password' => 'a-long-demo-password']);
    $this->get(route('google.callback', ['state' => session('state'), 'code' => 'valid-code']))->assertRedirect('/settings')->assertSessionHasErrors('google');
    expect($owner->fresh()->google_id)->toBeNull();
    $this->assertAuthenticatedAs($owner);
})->with(['email', 'subject']);

it('binds linking to the original session, account and password confirmation', function (string $change) {
    $owner = socialUser('existing');
    $this->googleProfile['email'] = $owner->email;
    $this->actingAs($owner)->post(route('google.link'), ['current_password' => 'a-long-demo-password']);
    $query = ['state' => session('state'), 'code' => 'valid-code'];
    if ($change === 'account') {
        $this->actingAs(socialUser('other'));
    }
    if ($change === 'logout') {
        $this->post('/logout');
    }
    if ($change === 'password') {
        $owner->update(['password' => 'new-long-password']);
    }
    if ($change === 'guest-login') {
        $this->post('/logout');
        $this->get(route('google.redirect'));
        $query['state'] = session('state');
        $this->actingAs($owner);
    }
    $this->get(route('google.callback', $query))->assertSessionHasErrors('google');
    expect($owner->fresh()->google_id)->toBeNull();
})->with(['account', 'logout', 'password', 'guest-login']);
