<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Listening circle') · Freek’s Records</title>
    <link rel="icon" href="/favicon.svg">
    @fonts
    @vite('resources/css/social.css')
    @stack('head')
</head>
<body class="@yield('body-class')">
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
    <a class="brand" href="/"><span class="brand-mark" aria-hidden="true"></span> Freek’s records<span class="brand-period">.</span></a>
    <nav aria-label="Main navigation">
        @auth
            <a href="/feed" @if(request()->is('feed') && !($startingCollection ?? false)) aria-current="page" @endif>Friends’ additions</a>
            <a href="/friends" @if(request()->is('friends')) aria-current="page" @endif>Friends</a>
            <a href="{{ route('profile', auth()->user()->handle) }}">My collection</a>
            <a href="/notifications">Notifications</a>
            <a href="/settings">Settings</a>
            <form method="post" action="/logout">@csrf<x-social.button variant="text">Sign out</x-social.button></form>
        @else
            <a href="{{ route('home') }}#how-it-works">How it works</a><x-social.button class="small" href="/login">Sign in</x-social.button>
        @endauth
    </nav>
</header>
<main id="main" class="page">
    @if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="errors" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@unless($startingCollection ?? false)
<footer class="site-footer"><span>Good records. Good company.</span><a href="/collection">Back to Freek’s collection</a></footer>
@endunless
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
