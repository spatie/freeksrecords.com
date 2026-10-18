@extends('social.layout')
@section('title', 'Friends’ additions')
@section('content')
<p class="eyebrow">The listening circle / Latest additions</p>
<h1>Fresh on their shelves<span>.</span></h1>
<p class="lede">From your friends. Newest first, always.</p>
<div class="feed-layout">
    <section aria-label="Friends’ additions" class="feed-main">
        <div class="record-grid">
            @forelse($records as $record)@include('social.card', ['showOwner' => true])
            @empty<section class="empty panel"><h2>Make room for good company.</h2><p>New additions will appear when accepted friends share them. Historical first imports stay off the feed.</p><a class="button" href="/friends">Find friends ↗</a></section>@endforelse
        </div>
        {{ $records->links() }}
    </section>
    <aside class="feed-sidebar" aria-label="Your collection and requests">
        <section class="panel"><p class="eyebrow">Your shelf</p><h2>My collection</h2><p>{{ $collectionCount }} {{ Str::plural('release', $collectionCount) }}</p><a href="{{ route('profile', auth()->user()->handle) }}">View my collection →</a><p class="muted spaced"><a href="/settings">Manage Discogs & privacy</a></p></section>
        <section class="panel"><h2>Friend requests</h2>
            @forelse($incomingRequests as $person)
                <div class="incoming-request"><a href="{{ route('profile', $person->handle) }}">{{ $person->name }}</a><p class="muted">{{ '@'.$person->handle }}</p><form class="actions" method="post" action="{{ route('friend.action', $person) }}">@csrf<button name="action" value="accept">Accept</button><button class="secondary" name="action" value="decline">Decline</button></form></div>
            @empty<p class="muted">No incoming requests. Find your people by their handle.</p>@endforelse
            <a class="request-link" href="/friends">Find friends →</a>
        </section>
    </aside>
</div>
@endsection
