@extends('social.layout')
@php($startingCollection = auth()->user()->collection_imported_at === null && ($collectionCount === 0 || auth()->user()->discogs_token))
@section('title', $startingCollection ? 'Add your records' : 'Friends’ additions')
@section('content')
@if($startingCollection)
    <section class="onboarding" aria-labelledby="onboarding-title">
        <ol class="setup-steps" aria-label="Account setup"><li>Account created</li><li>Email verified</li><li aria-current="step">Import collection</li></ol>
        <h1 id="onboarding-title">Add your records</h1>
        @if(auth()->user()->discogs_token)
            <x-social.import-status :user="auth()->user()" :count="$collectionCount" />
        @else
            <p class="lede">Bring your collection over from Discogs. Your records will appear on your own shelf, ready to browse and share.</p>
            <form method="post" action="/discogs/connect">@csrf<x-social.button>Connect Discogs</x-social.button></form>
            <p class="onboarding-detail">You’ll approve the connection on Discogs. We never see your password. {{ auth()->user()->visibility === 'only_me' ? 'Only you can see your collection.' : 'Your current sharing preference applies to imported records.' }}</p>
            <details class="discogs-help"><summary>Don’t have a collection on Discogs yet?</summary><p>Add your vinyl, CDs or cassettes to <a href="https://www.discogs.com/my" target="_blank" rel="noopener noreferrer">your Discogs collection</a>, then come back and connect. Records are added on Discogs, not directly on Plaat Club.</p></details>
        @endif
    </section>
@else
    <x-social.page-heading title="Friends’ additions" description="The newest records on your friends’ shelves, in the order they added them." />
@endif
@if(!$startingCollection || $records->isNotEmpty() || $incomingRequests->isNotEmpty())
<div class="feed-layout">
    <section aria-label="Friends’ additions" class="feed-main">
        @if($startingCollection && $records->isNotEmpty())<h2>Friends’ additions</h2>@endif
        <div class="record-grid">
            @forelse($records as $record)@include('social.card', ['showOwner' => true])
            @empty
                @unless($startingCollection)<section class="empty panel"><h2>Nothing new yet.</h2><p>Accepted friends’ new records appear here. Your first import stays off their feeds.</p><div class="actions"><x-social.button href="/friends">Find friends</x-social.button><a href="{{ route('collections') }}">Browse collections</a></div></section>@endunless
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
