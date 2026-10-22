<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

        <meta property="og:locale" content="en_GB">
        <meta name="twitter:site" content="@freekmurze">
        <meta name="theme-color" content="#292a22">

        @fonts

        @viteReactRefresh
        @if(str_starts_with($page['component'], 'social/'))
            @vite(['resources/css/social.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @else
            @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @endif
        {{-- Fallback for when the SSR server is unavailable. With SSR, RecordRoomHead renders these tags. --}}
        <x-inertia::head>
            @if($page['component'] === 'welcome')
                @php
                    $shared = $page['props']['sharedRecord'] ?? null;
                    $social = $page['props']['social'];
                    $title = $shared ? "{$shared['title']} by {$shared['artist']}" : $social['title'];
                    $description = $shared['description'] ?? $social['description'];
                    $url = $shared['url'] ?? $social['url'];
                    $image = $shared['image'] ?? $social['image'];
                    $imageAlt = $shared ? "{$shared['title']} sleeve and vinyl, by {$shared['artist']}" : $social['imageAlt'];
                    $structuredData = [
                        '@context' => 'https://schema.org',
                        '@type' => $shared ? 'MusicAlbum' : 'CollectionPage',
                        'name' => $shared['title'] ?? $title,
                        'description' => $description,
                        'url' => $url,
                        'image' => $image,
                        'inLanguage' => 'en',
                        ...($shared ? ['byArtist' => ['@type' => 'MusicGroup', 'name' => $shared['artist']]] : [
                            'isPartOf' => ['@type' => 'WebSite', 'name' => $title, 'url' => $url],
                            'author' => ['@type' => 'Person', 'name' => 'Freek Van der Herten'],
                        ]),
                    ];
                @endphp
                <title>{{ $title }}</title>
                <script data-inertia="structured-data" type="application/ld+json">{!! json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
                <meta data-inertia="description" name="description" content="{{ $description }}">
                <meta data-inertia="og:type" property="og:type" content="{{ $shared ? 'music.album' : 'website' }}">
                <meta data-inertia="og:site_name" property="og:site_name" content="Plaat Club">
                <meta data-inertia="og:title" property="og:title" content="{{ $title }}">
                <meta data-inertia="og:description" property="og:description" content="{{ $description }}">
                <meta data-inertia="og:url" property="og:url" content="{{ $url }}">
                <meta data-inertia="og:image" property="og:image" content="{{ $image }}">
                <meta data-inertia="og:image:width" property="og:image:width" content="1200">
                <meta data-inertia="og:image:height" property="og:image:height" content="630">
                <meta data-inertia="og:image:type" property="og:image:type" content="image/jpeg">
                <meta data-inertia="og:image:alt" property="og:image:alt" content="{{ $imageAlt }}">
                <meta data-inertia="twitter:card" name="twitter:card" content="summary_large_image">
                <meta data-inertia="twitter:title" name="twitter:title" content="{{ $title }}">
                <meta data-inertia="twitter:description" name="twitter:description" content="{{ $description }}">
                <meta data-inertia="twitter:image" name="twitter:image" content="{{ $image }}">
                <meta data-inertia="twitter:image:alt" name="twitter:image:alt" content="{{ $imageAlt }}">
                <link data-inertia="canonical" rel="canonical" href="{{ $url }}">
            @elseif(isset($page['props']['socialPreview']))
                @php($preview = $page['props']['socialPreview'])
                <title>{{ $preview['title'] }}</title>
                <meta data-inertia="description" name="description" content="{{ $preview['description'] }}">
                @foreach([
                    'og:site_name' => 'Plaat Club', 'og:type' => $preview['type'],
                    'og:title' => $preview['title'], 'og:description' => $preview['description'],
                    'og:url' => $preview['url'], 'og:image' => $preview['image'],
                    'og:image:alt' => $preview['imageAlt'], 'og:image:width' => '1200',
                    'og:image:height' => '630', 'og:image:type' => 'image/jpeg',
                ] as $property => $content)
                    <meta data-inertia="{{ $property }}" property="{{ $property }}" content="{{ $content }}">
                @endforeach
                @foreach([
                    'twitter:card' => 'summary_large_image', 'twitter:title' => $preview['title'],
                    'twitter:description' => $preview['description'], 'twitter:image' => $preview['image'],
                    'twitter:image:alt' => $preview['imageAlt'],
                ] as $name => $content)
                    <meta data-inertia="{{ $name }}" name="{{ $name }}" content="{{ $content }}">
                @endforeach
                <link data-inertia="canonical" rel="canonical" href="{{ $preview['url'] }}">
            @else
                <title>{{ config('app.name', 'Laravel') }}</title>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
