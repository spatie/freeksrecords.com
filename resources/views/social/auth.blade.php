@extends('social.layout')
@section('title', match($mode) { 'register' => 'Create an account', 'forgot' => 'Reset your password', 'reset' => 'Choose a password', 'verify' => 'Check your inbox', default => 'Sign in' })
@section('body-class', 'auth-page')
@push('head')
    @vite('resources/css/auth.css')
@endpush
@section('content')
<div class="auth-layout">
    <section class="auth-welcome" aria-labelledby="auth-welcome-title">
        <h1 id="auth-welcome-title">{{ match($mode) { 'register' => 'Make room for your records.', 'forgot' => 'Let’s get you back in.', 'reset' => 'A fresh start, same good records.', 'verify' => 'You’re in good company.', default => 'Good to have you back.' } }}</h1>
        <p class="auth-introduction">{{ match($mode) { 'register' => 'Sync your Discogs collection and choose who sees it.', 'forgot' => 'We’ll email you a link to reset your password so you can return to your collection.', 'reset' => 'Choose a new password for your account. Your collection will be right where you left it.', 'verify' => 'Verify your email to bring in your records from Discogs and make yourself at home.', default => 'Your collection and your friends’ latest finds are waiting.' } }}</p>
        <div class="auth-sleeves" aria-hidden="true">
            @foreach(['19772260', '24675032', '495664', '28446325', '36912054'] as $cover)
                <img src="/images/records/{{ $cover }}.jpg" alt="" width="240" height="240" draggable="false">
            @endforeach
        </div>
        @if($mode === 'register')<p class="auth-reassurance">Private until you choose to share.</p>@endif
    </section>
    <section class="auth-panel" aria-labelledby="auth-form-title">
    <h2 id="auth-form-title">@yield('title')</h2>
    @if($mode === 'verify')
        <p>We sent a link to <strong>{{ auth()->user()->email }}</strong>. Open that email and follow the link to verify your address.</p>
        <p class="muted">Can’t find it? Check your spam folder, or send a fresh link.</p>
        <form method="post" action="/email/verification-notification">@csrf<x-social.button>Resend verification email</x-social.button></form>
        <form class="spaced" method="post" action="/logout">@csrf<x-social.button variant="text">Wrong email? Sign out</x-social.button></form>
    @else
        @if($mode === 'forgot')<p class="auth-form-intro">Enter the email address you use for Plaat Club.</p>
        @elseif($mode === 'reset')<p class="auth-form-intro">Save your new password, then sign in with it.</p>@endif
        <form class="stack" method="post" action="{{ match($mode) { 'register' => '/register', 'forgot' => '/forgot-password', 'reset' => '/reset-password', default => '/login' } }}">
            @csrf
            @if($mode === 'reset')<input type="hidden" name="token" value="{{ $token }}">@endif
            <x-social.input name="email" label="Email" type="email" autocomplete="email" :value="request('email')" required />
            @if($mode !== 'forgot')
                <x-social.input name="password" label="Password" type="password" :autocomplete="$mode === 'login' ? 'current-password' : 'new-password'" :minlength="$mode === 'login' ? null : 12" :hint="$mode === 'login' ? null : 'At least 12 characters. A few words work well.'" required />
                @if($mode !== 'login')<x-social.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" minlength="12" required />@endif
            @endif
            <x-social.button>{{ match($mode) { 'register' => 'Create account', 'forgot' => 'Send reset link', 'reset' => 'Save new password', default => 'Sign in' } }}</x-social.button>
        </form>
        @if($mode === 'login')<div class="auth-links"><a href="/forgot-password">Forgot password?</a><a href="/register">Create an account</a></div>
        @elseif($mode === 'register')<p>Already have an account? <a href="/login">Sign in</a></p>
        @else<p>Remember your password? <a href="/login">Sign in</a></p>@endif
    @endif
    </section>
</div>
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
