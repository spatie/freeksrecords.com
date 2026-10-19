<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Your collection') · Plaat Club</title>
    <link rel="icon" href="/favicon.svg">
    @fonts
    @vite('resources/css/social.css')
    @stack('head')
</head>
<body class="@yield('body-class')">
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
    <a class="brand" href="/"><span class="brand-mark" aria-hidden="true"></span> Plaat Club<span class="brand-period">.</span></a>
    <nav aria-label="Main navigation">
        @auth
            @php($startingCollection = $startingCollection ?? (auth()->user()->collection_imported_at === null && (auth()->user()->discogs_token || !auth()->user()->records()->exists())))
            @if(auth()->user()->hasVerifiedEmail())
                <a href="{{ route('profile', auth()->user()->handle) }}" @if(request()->routeIs('profile') && request()->route('user')?->id === auth()->id()) aria-current="page" @endif>My collection</a>
                <a href="/feed" @if(request()->is('feed')) aria-current="page" @endif>{{ ($startingCollection ?? false) ? 'Add records' : 'Friends’ additions' }}</a>
                <a href="/friends" @if(request()->is('friends')) aria-current="page" @endif>Friends</a>
                <a href="{{ route('collections') }}" @if(request()->routeIs('collections')) aria-current="page" @endif>Browse</a>
                <x-social.notifications />
                <a href="/settings" @if(request()->is('settings')) aria-current="page" @endif>Settings</a>
            @else
                <a href="{{ route('verification.notice') }}" aria-current="page">Verify email</a>
            @endif
            <form method="post" action="/logout">@csrf<x-social.button variant="text">Sign out</x-social.button></form>
        @else
            <a href="{{ route('collections') }}" @if(request()->routeIs('collections')) aria-current="page" @endif>Browse collections</a>
            <a href="/login" @if(request()->routeIs('login')) aria-current="page" @endif>Sign in</a>
            @unless(request()->routeIs('register', 'login', 'password.request', 'password.reset'))<x-social.button class="small" href="/register">Create account</x-social.button>@endunless
        @endauth
    </nav>
</header>
<main id="main" class="page">
    @if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="errors" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->messages() as $field => $messages)<li>@if(in_array($field, ['name', 'handle', 'email', 'password', 'password_confirmation']))<a href="#{{ $field }}">{{ $messages[0] }}</a>@else{{ $messages[0] }}@endif</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@if(request()->is('/', 'feed'))
<script>
    (() => {
        const hash = window.location.hash;
        if (/^#record-\d+$/.test(hash) || /^#(?:genre|artist|decade|q)=/.test(hash)) {
            window.location.replace('/collection' + hash);
        }
    })();
</script>
@endif
@stack('scripts')
</body>
</html>
