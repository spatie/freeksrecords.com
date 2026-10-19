@extends('social.layout')
@section('title', 'A shelf for every record')
@section('body-class', 'home-page')
@section('content')
<section class="landing-hero">
    <div class="landing-copy">
        <h1>A shelf for every record.</h1>
        <p class="lede">Your vinyl, CDs and cassettes, in one collection. Bring it over from Discogs and discover what your friends are adding.</p>
        <div class="landing-actions"><x-social.button href="/register">Create account</x-social.button><a href="{{ route('collections') }}">Browse collections</a></div>
        <p class="landing-note">Private until you choose to share.<br>Already a member? <a href="/login">Sign in</a></p>
    </div>
    @if($featured = $collectors->first())
        <figure class="record-wall">
            <div class="record-wall-covers">
                @foreach($featured->records as $record)
                    <a href="{{ route('shelf.record', $record) }}" aria-label="{{ $record->payload['displayTitle'] }} by {{ $record->payload['artist'] }}">
                        <img src="{{ $record->payload['cover'] }}" alt="{{ $record->payload['displayTitle'] }} by {{ $record->payload['artist'] }}" width="260" height="260" @if($loop->index > 2) loading="lazy" @endif>
                    </a>
                @endforeach
            </div>
            <figcaption>From <a href="{{ route('profile', $featured->handle) }}">{{ $featured->name }}’s collection</a></figcaption>
        </figure>
    @endif
</section>
<section class="public-collections" aria-labelledby="collections-title">
    <div class="section-heading"><div><h2 id="collections-title">Open collections</h2><p>Have a look through the shelves people have chosen to share.</p></div><a href="{{ route('collections') }}">All public collections</a></div>
    <div class="collection-previews">
        @forelse($collectors as $collector)
            <x-social.collection-preview :collector="$collector" />
        @empty
            <p class="muted">The shelves are still filling up. Yours could be the first.</p>
        @endforelse
    </div>
</section>
@endsection
