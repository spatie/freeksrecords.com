@extends('social.layout')
@php($startingCollection = $collectionCount === 0 && auth()->user()->collection_imported_at === null)
@section('title', $startingCollection ? 'Add your records' : 'Friends’ additions')
@section('content')
@if($startingCollection)
    <section class="hero" aria-labelledby="onboarding-title">
        <h1 id="onboarding-title">Add your records<span>.</span></h1>
        @if(auth()->user()->discogs_token)
            <p class="lede">{{ auth()->user()->sync_status ?? 'Discogs is connected.' }}</p>
            <x-social.button href="/settings">View import status</x-social.button>
        @else
            <p class="lede">Connect Discogs to bring your collection here.</p>
            <form method="post" action="/discogs/connect">@csrf<x-social.button>Connect Discogs</x-social.button></form>
        @endif
    </section>
@else
    <h1>Friends’ additions<span>.</span></h1>
@endif
@if(!$startingCollection || $records->isNotEmpty() || $incomingRequests->isNotEmpty())
<div class="feed-layout">
    <section aria-label="Friends’ additions" class="feed-main">
        @if($startingCollection && $records->isNotEmpty())<h2>Friends’ additions</h2>@endif
        <div class="record-grid">
            @forelse($records as $record)@include('social.card', ['showOwner' => true])
            @empty
                @unless($startingCollection)<section class="empty panel"><h2>Nothing new yet.</h2><x-social.button href="/friends">Find friends</x-social.button></section>@endunless
            @endforelse
        </div>
        {{ $records->links() }}
    </section>
    <aside class="feed-sidebar" aria-label="Your collection and requests">
        @unless($startingCollection)
            <section class="panel"><h2>My collection</h2><p>{{ $collectionCount }} {{ Str::plural('release', $collectionCount) }}</p><a href="{{ route('profile', auth()->user()->handle) }}">View my collection</a></section>
        @endunless
        @if($incomingRequests->isNotEmpty())
        <section class="panel"><h2>Friend requests</h2>
            @foreach($incomingRequests as $person)
                <div class="incoming-request"><a href="{{ route('profile', $person->handle) }}">{{ $person->name }}</a><p class="muted">{{ '@'.$person->handle }}</p><form class="actions" method="post" action="{{ route('friend.action', $person) }}">@csrf<x-social.button name="action" value="accept">Accept</x-social.button><x-social.button variant="secondary" name="action" value="decline">Decline</x-social.button></form></div>
            @endforeach
        </section>
        @endif
    </aside>
</div>
@endif
@endsection
