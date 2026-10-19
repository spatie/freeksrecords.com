@extends('social.layout')
@section('title', match($mode) { 'register' => 'Create an account', 'forgot' => 'Reset your password', 'reset' => 'Choose a password', 'verify' => 'Check your inbox', default => 'Sign in' })
@section('content')
<section class="auth-panel panel">
    <h1 class="compact">@yield('title')</h1>
    @if($mode === 'verify')
        <p>We sent a link to <strong>{{ auth()->user()->email }}</strong>. Open it to verify your email, then bring in your records from Discogs.</p>
        <p class="muted">Can’t find it? Check your spam folder, or send a fresh link.</p>
        <form method="post" action="/email/verification-notification">@csrf<x-social.button>Resend verification email</x-social.button></form>
        <form class="spaced" method="post" action="/logout">@csrf<x-social.button variant="text">Wrong email? Sign out</x-social.button></form>
    @else
        @if($mode === 'register')<p>Your collection comes next. Connect Discogs after verifying your email. Everything starts private.</p>
        @elseif($mode === 'forgot')<p>Enter your account’s email and we’ll send you a reset link.</p>@endif
        <form class="stack" method="post" action="{{ match($mode) { 'register' => '/register', 'forgot' => '/forgot-password', 'reset' => '/reset-password', default => '/login' } }}">
            @csrf
            @if($mode === 'reset')<input type="hidden" name="token" value="{{ $token }}">@endif
            @if($mode === 'register')
                <x-social.input name="name" label="Display name" autocomplete="name" maxlength="80" required />
                <x-social.input name="handle" label="Username" autocomplete="username" pattern="[a-zA-Z0-9_]{3,30}" minlength="3" maxlength="30" hint="Your address: plaat.club/username. 3–30 letters, numbers or underscores." required />
            @endif
            <x-social.input name="email" label="Email" type="email" autocomplete="email" :value="request('email')" required />
            @if($mode !== 'forgot')
                <x-social.input name="password" label="Password" type="password" :autocomplete="$mode === 'login' ? 'current-password' : 'new-password'" :minlength="$mode === 'login' ? null : 12" :hint="$mode === 'login' ? null : 'At least 12 characters. A few words work well.'" required />
                @if($mode !== 'login')<x-social.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" minlength="12" required />@endif
            @endif
            <x-social.button>{{ match($mode) { 'register' => 'Create account', 'forgot' => 'Send reset link', 'reset' => 'Save new password', default => 'Sign in' } }}</x-social.button>
        </form>
        @if($mode === 'login')<div class="auth-links"><a href="/forgot-password">Forgot password?</a><a href="/register">Create an account</a></div>@else<p>Already have an account? <a href="/login">Sign in</a></p>@endif
    @endif
</section>
@if($mode === 'login' && app()->environment('local') && config('services.social_demo') && Route::has('loginLinkLogin'))
    @php($demoEmails = App\Models\User::query()->whereIn('email', array_keys(config('login-link.demo_users')))->pluck('email'))
    @if($demoEmails->isNotEmpty())
        <section class="demo-logins" aria-label="Local demo account switcher">
            <p><strong>Local demo accounts</strong> — Switch without a password</p>
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
