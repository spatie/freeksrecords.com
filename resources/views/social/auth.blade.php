@extends('social.layout')
@section('title', match($mode) { 'register' => 'Join the circle', 'forgot' => 'Reset your password', 'reset' => 'Choose a password', 'verify' => 'Verify your email', default => 'Welcome back' })
@section('content')
<section class="auth-panel panel">
    <p class="eyebrow">Your listening circle</p>
    <h1 class="compact">@yield('title')<span>.</span></h1>
    @if($mode === 'verify')
        <p>We sent a verification link to your email. Verify it before connecting Discogs, adding friends, or interacting with records.</p>
        <form method="post" action="/email/verification-notification">@csrf<x-social.button>Resend verification email</x-social.button></form>
    @else
        <form class="stack" method="post" action="{{ match($mode) { 'register' => '/register', 'forgot' => '/forgot-password', 'reset' => '/reset-password', default => '/login' } }}">
            @csrf
            @if($mode === 'reset')<input type="hidden" name="token" value="{{ $token }}">@endif
            @if($mode === 'register')
                <label>Display name<input name="name" autocomplete="name" value="{{ old('name') }}" maxlength="80" required></label>
                <label>Profile handle<input name="handle" autocomplete="username" value="{{ old('handle') }}" pattern="[a-zA-Z0-9_]{3,30}" minlength="3" maxlength="30" required><small>3–30 letters, numbers or underscores. Your public profile address.</small></label>
            @endif
            <label>Email<input type="email" name="email" autocomplete="email" value="{{ old('email', request('email')) }}" required></label>
            @if($mode !== 'forgot')
                <label>Password<input type="password" name="password" autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" @if($mode !== 'login') minlength="12" @endif required></label>
                @if($mode !== 'login')<label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required><small>Use at least 12 characters.</small></label>@endif
            @endif
            <x-social.button>{{ match($mode) { 'register' => 'Create my account', 'forgot' => 'Send reset link', 'reset' => 'Save new password', default => 'Sign in' } }}</x-social.button>
        </form>
        @if($mode === 'login')<p><a href="/forgot-password">Forgot password?</a> · <a href="/register">Create an account</a></p>@else<p><a href="/login">Back to sign in</a></p>@endif
    @endif
</section>
@if($mode === 'login' && app()->environment('local') && config('services.social_demo') && Route::has('loginLinkLogin'))
    @php($demoEmails = App\Models\User::query()->whereIn('email', array_keys(config('login-link.demo_users')))->pluck('email'))
    @if($demoEmails->isNotEmpty())
        <section class="demo-logins" aria-label="Local demo account switcher">
            <p><strong>Local demo accounts</strong> · Switch without a password</p>
            <div class="actions">
                @foreach(config('login-link.demo_users') as $email => $label)
                    @if($demoEmails->contains($email))
                        <x-dynamic-component component="login-link" :email="$email" :label="$label" redirect-url="/feed" class="button secondary" />
                    @endif
                @endforeach
            </div>
            <small>Alex has a full demo shelf. Casey’s collection is private; Jamie’s shelf is empty.</small>
        </section>
    @endif
@endif
@endsection
