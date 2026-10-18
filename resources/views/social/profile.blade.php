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
</div>
@if(!$visible)
    <section class="empty panel"><h2>A private shelf.</h2><p>This collection isn’t shared with you. Only the collector’s name and handle are public.</p></section>
@else
    @if(count($records))
        <div id="profile-shelf" class="profile-record-room"></div>
        <script id="profile-shelf-data" type="application/json">{!! json_encode($records, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        <noscript><p>Enable JavaScript to browse this record shelf.</p></noscript>
    @else
    <section class="empty panel">
        <h2>A shelf waiting for its first record.</h2><p>{{ auth()->id() === $person->id ? 'Connect Discogs in Settings to bring your collection home.' : 'Nothing here yet. Check back after the first import.' }}</p>
    </section>
    @endif
@endif
@endsection
