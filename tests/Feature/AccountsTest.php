<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

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

it('seeds repeatable synthetic local data without touching Freek and refuses production seeding', function () {
    Notification::fake();
    app()->detectEnvironment(fn () => 'local');
    $password = User::freek()->password;
    $this->artisan('social:seed-demo')->assertSuccessful();
    $this->artisan('social:seed-demo')->assertSuccessful();
    $this->assertDatabaseCount('users', 6);
    $this->assertDatabaseCount('collection_records', 9);
    $this->assertDatabaseCount('friendships', 4);
    $this->assertDatabaseCount('social_notifications', 1);
    expect(User::freek()->password)->toBe($password);
    expect(User::query()->where('handle', 'casey')->first()->visibility)->toBe('only_me');
    Notification::assertNothingSent();
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('social:seed-demo')->assertFailed();
});
