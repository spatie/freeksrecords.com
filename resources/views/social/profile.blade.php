@extends('social.layout')
@section('title', $person->name.'’s collection')
@section('body-class', 'collection-page')
@if($visible)
    @push('head')
        @viteReactRefresh
        @vite('resources/js/profile-shelf.tsx')
    @endpush
@endif
@section('content')
<div class="collector-heading">
    <h1>{{ $person->name }}’s collection</h1>
    <p>{{ '@'.$person->handle }}@if($visible) · {{ count($records) }} {{ Str::plural('release', count($records)) }}@endif</p>
    @if(auth()->id() === $person->id)
        <a class="collection-visibility" href="/settings">{{ match($person->visibility) { 'everyone' => 'Public collection', 'friends' => 'Shared with friends', default => 'Only you can see this' } }}</a>
    @elseif(auth()->user()?->hasVerifiedEmail())
        <a class="collection-visibility" href="{{ route('friends', ['q' => $person->handle]) }}">Friendship with {{ $person->name }}</a>
    @endif
</div>
@if(!$visible)
    <section class="empty panel"><h2>A private collection</h2><p>This collection isn’t shared with you. Only the collector’s name and username are public.</p></section>
@else
    @if(count($records))
        <div id="profile-shelf" class="profile-record-room"></div>
        <script id="profile-shelf-data" type="application/json">{!! json_encode($records, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        <noscript><p>Enable JavaScript to browse this record shelf.</p></noscript>
    @else
    <section class="empty panel">
        <h2>A shelf waiting for its first record.</h2><p>{{ auth()->id() === $person->id ? 'Import your Discogs collection to fill your shelf.' : 'Nothing here yet. Check back after the first import.' }}</p>
        @if(auth()->id() === $person->id)<x-social.button :href="$person->collection_imported_at === null ? route('feed') : route('settings')">{{ $person->collection_imported_at === null ? 'Add your records' : 'Manage Discogs connection' }}</x-social.button>@endif
    </section>
    @endif
@endif
@endsection
