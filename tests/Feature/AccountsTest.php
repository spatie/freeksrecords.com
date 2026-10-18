<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

it('shows local demo login controls only on the login page', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['services.social_demo' => true]);
    $alex = socialUser('alex');

    $this->get('/login')->assertOk()->assertSeeText('Local demo accounts')->assertSee(route('loginLinkLogin'), false);

    foreach (['/', '/register', '/forgot-password', '/reset-password/preview-token', '/people/alex'] as $path) {
        $this->get($path)->assertOk()->assertDontSee('demo-logins')->assertDontSee('laravel-login-link-login');
    }

    $this->actingAs($alex);
    foreach (['/people/alex', '/feed', '/settings'] as $path) {
        $this->get($path)->assertOk()->assertDontSee('demo-logins')->assertDontSee('laravel-login-link-login');
    }

    $alex->update(['email_verified_at' => null]);
    $this->get('/verify-email')->assertOk()->assertDontSee('demo-logins')->assertDontSee('laravel-login-link-login');
});

it('switches existing local demo accounts without passwords and ignores forged package selectors', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['services.social_demo' => true]);
    $alex = socialUser('alex');
    $robin = socialUser('robin');
    $this->get('/login')->assertOk()->assertSeeText('Local demo accounts')->assertSeeText('Alex')->assertSeeText('Robin')->assertDontSeeText('Sam');

    $this->withSession(['_token' => 'demo-login'])->post(route('loginLinkLogin'), ['email' => $alex->email, '_token' => 'demo-login'])->assertRedirect('/feed');
    $this->assertAuthenticatedAs($alex);
    $this->get('/feed')->assertOk();

    $forged = ['key' => User::freek()->id, 'user_attributes' => json_encode(['email' => User::freek()->email]), 'user_model' => 'AnUntrustedModel', 'guard' => 'not-a-guard', 'redirect_url' => 'https://untrusted.example'];
    $this->withSession(['_token' => 'demo-login'])->post(route('loginLinkLogin'), [...$forged, 'email' => $robin->email, '_token' => 'demo-login'])->assertRedirect('/feed');
    $this->assertAuthenticatedAs($robin);
    $this->get('/feed')->assertOk();

    $this->withSession(['_token' => 'demo-login'])->post(route('loginLinkLogin').'?'.http_build_query($forged), ['email' => $alex->email, '_token' => 'demo-login'])->assertRedirect('/feed');
    $this->assertAuthenticatedAs($alex);
    $this->get('/feed')->assertOk();
});

it('does not allow login links for Freek, arbitrary accounts or missing demo accounts', function ($email) {
    app()->detectEnvironment(fn () => 'local');
    config(['services.social_demo' => true]);
    socialUser('outsider');
    $this->withSession(['_token' => 'demo-login'])->post(route('loginLinkLogin'), ['email' => $email, '_token' => 'demo-login'])->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseCount('users', 2);
})->with(['freek@spatie.be', 'outsider@example.test', 'alex@example.test', null, ['alex@example.test']]);

it('hides and disables login links outside explicitly enabled local demos', function (string $environment, bool $enabled) {
    app()->detectEnvironment(fn () => $environment);
    config(['services.social_demo' => $enabled]);
    $alex = socialUser('alex');
    $this->get('/login')->assertOk()->assertDontSee('Local demo accounts')->assertDontSee('laravel-login-link-login');
    $this->withSession(['_token' => 'demo-login'])->post(route('loginLinkLogin'), ['email' => $alex->email, '_token' => 'demo-login'])->assertNotFound();
    $this->assertGuest();
})->with([['production', true], ['staging', true], ['testing', true], ['local', false]]);

it('renders account emails with a shared inline layout and a working action link', function (string $notification, string $actionPath) {
    $user = socialUser('mailrecipient');
    $message = $notification === 'verify' ? new VerifyEmail : new ResetPassword('preview-token');
    $html = (string) $message->toMail($user)->render();

    expect($html)->toContain(config('app.name'), 'class="inner-body"', '#f5f1e7', '#a64028', 'class="button button-primary"', $actionPath)
        ->not->toContain('<link rel="stylesheet"');
})->with([['verify', '/verify-email/'], ['reset', '/reset-password/preview-token']]);

it('registers a private account, verifies the inbox, and authenticates securely', function () {
    Notification::fake();
    $this->post('/register', ['name' => 'New Collector', 'handle' => 'New_Collector', 'email' => 'NEW@example.test', 'password' => 'a-long-demo-password', 'password_confirmation' => 'a-long-demo-password', 'visibility' => 'everyone', 'email_verified_at' => now(), 'legacy_owner' => true])->assertRedirect('/verify-email');
    $user = User::query()->where('email', 'new@example.test')->firstOrFail();
    expect($user->handle)->toBe('new_collector')->and($user->visibility)->toBe('only_me')->and($user->legacy_owner)->toBeFalse()->and($user->email_verified_at)->toBeNull()->and($user->digest_enabled)->toBeTrue();
    expect(Hash::check('a-long-demo-password', $user->password))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->get('/feed')->assertRedirect('/verify-email');
    $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertRedirect('/feed');
    $this->get('/feed')->assertOk();
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => $user->email, 'password' => 'a-long-demo-password'])->assertRedirect('/feed');
    $this->assertAuthenticatedAs($user);
});

it('reserves Freek’s identity and rejects duplicate or invalid handles and weak passwords', function () {
    $data = ['name' => 'Someone', 'handle' => 'freek', 'email' => 'other@example.test', 'password' => 'short', 'password_confirmation' => 'short'];
    $this->post('/register', $data)->assertSessionHasErrors(['handle', 'password']);
    $this->post('/register', [...$data, 'handle' => 'valid_handle', 'email' => 'FREEK@spatie.be'])->assertSessionHasErrors('email');
    $this->post('/register', [...$data, 'handle' => '<invalid>'])->assertSessionHasErrors('handle');
    expect(User::freek()->email_verified_at)->toBeNull();
});

it('resets passwords through a single-use expiring email token', function () {
    Notification::fake();
    $user = socialUser('reset');
    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);
    $token = Password::createToken($user);
    $data = ['email' => $user->email, 'token' => $token, 'password' => 'new-long-password', 'password_confirmation' => 'new-long-password'];
    $this->post('/reset-password', [...$data, 'token' => 'invalid'])->assertSessionHasErrors('email');
    $this->post('/reset-password', $data)->assertRedirect('/login');
    expect(Hash::check('new-long-password', $user->fresh()->password))->toBeTrue();
    $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    $token = Password::createToken($user);
    $this->travel(61)->minutes();
    $this->post('/reset-password', [...$data, 'token' => $token])->assertSessionHasErrors('email');
});

it('updates only allowed profile fields and keeps the legacy collection public', function () {
    $user = socialUser('settings');
    $this->actingAs($user)->patch('/settings', ['name' => 'New name', 'handle' => 'Changed', 'visibility' => 'friends', 'comments_enabled' => false, 'digest_enabled' => false, 'email' => 'stolen@example.test', 'legacy_owner' => true])->assertSessionHasNoErrors();
    expect($user->fresh()->handle)->toBe('changed')->and($user->fresh()->comments_enabled)->toBeFalse()->and($user->fresh()->digest_enabled)->toBeFalse()->and($user->fresh()->email)->toBe('settings@example.test');
    $this->get('/people/changed')->assertOk()->assertSee('New name');
    $this->post('/logout');
    $freek = User::freek();
    $freek->update(['email_verified_at' => now()]);
    $this->actingAs($freek)->patch('/settings', ['name' => $freek->name, 'handle' => 'freek', 'visibility' => 'only_me', 'comments_enabled' => true, 'digest_enabled' => true])->assertSessionHasErrors('visibility');
});

it('provisions Freek only via his reserved inbox without leaking a password or token', function () {
    Notification::fake();
    $password = User::freek()->password;
    $this->artisan('social:provision-freek')->assertSuccessful();
    Notification::assertSentTo(User::freek(), ResetPassword::class);
    expect(User::freek()->password)->toBe($password);
});

it('seeds repeatable real catalog local data without touching Freek and refuses production seeding', function () {
    Notification::fake();
    Http::preventStrayRequests();
    app()->detectEnvironment(fn () => 'local');
    $password = User::freek()->password;
    $this->artisan('social:seed-demo')->assertSuccessful();
    $this->artisan('social:seed-demo')->assertSuccessful();
    $this->assertDatabaseCount('users', 6);
    $this->assertDatabaseCount('collection_records', 300);
    $this->assertDatabaseCount('friendships', 4);
    $this->assertDatabaseCount('social_notifications', 3);
    $this->assertDatabaseCount('comments', 1);
    $this->assertDatabaseCount('appreciations', 1);
    $alex = User::query()->where('handle', 'alex')->firstOrFail();
    expect($alex->friends()->pluck('handle')->sort()->values()->all())->toBe(['casey', 'robin', 'sam'])
        ->and($alex->records()->where('instance_id', 900030)->firstOrFail()->private_note)->toContain('birthday gift')
        ->and(User::query()->where('handle', 'jamie')->firstOrFail()->records()->count())->toBe(0);
    expect(User::freek()->password)->toBe($password);
    foreach (['alex' => 120, 'robin' => 90, 'sam' => 60, 'casey' => 30] as $handle => $count) {
        $records = User::query()->where('handle', $handle)->firstOrFail()->records()->get();
        expect($records)->toHaveCount($count)
            ->and($records->pluck('payload.artist')->unique()->count())->toBeGreaterThan(15)
            ->and($records->where('is_activity', true)->count())->toBeGreaterThan(0)
            ->and($records->where('is_activity', false)->count())->toBeGreaterThan(0);
        foreach (['Vinyl', 'CD', 'Cassette'] as $format) {
            expect($records->contains(fn ($record) => in_array($format, $record->formats(), true)))->toBeTrue();
        }
        foreach ($records as $record) {
            expect($record->payload['id'])->toBe($record->discogs_release_id)
                ->and(is_file(public_path($record->payload['cover'])))->toBeTrue()
                ->and($record->payload['tracks'])->not->toBeEmpty();
        }
    }
    expect(User::query()->where('handle', 'casey')->first()->visibility)->toBe('only_me');
    $this->actingAs(User::query()->where('handle', 'jamie')->firstOrFail())->get('/feed')
        ->assertOk()->assertSee('Add your records')->assertSee('Connect Discogs');
    Notification::assertNothingSent();
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('social:seed-demo')->assertFailed();
});
