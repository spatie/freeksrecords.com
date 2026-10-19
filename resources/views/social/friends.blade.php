@extends('social.layout')
@section('title', 'Your friends')
@section('content')
<x-social.page-heading title="Friends" description="Find someone by username and send a request. Once they accept, you’ll see the records they share with friends." />
<form class="search" method="get" action="/friends"><label for="find-handle">Find someone by profile handle</label><div class="actions"><input id="find-handle" name="q" value="{{ $query }}" maxlength="30" placeholder="e.g. robin"><x-social.button>Search</x-social.button><a href="/friends">My connections</a></div></form>
<div class="people-list">
@forelse($people as $person)
    @php($connection = $connections->first(fn ($f) => in_array($person->id, [$f->user_low, $f->user_high], true)))
    <article class="person-row panel">
        <div><h2><a href="{{ route('profile', $person->handle) }}">{{ $person->name }}</a></h2><p>{{ '@'.$person->handle }}</p></div>
        <form class="actions" method="post" action="{{ route('friend.action', $person) }}">@csrf
            @if(!$connection)<x-social.button name="action" value="request">Send friend request</x-social.button>
            @elseif($connection->accepted_at)<span class="badge">Friends</span><x-social.button variant="secondary" name="action" value="unfriend">Unfriend</x-social.button>
            @elseif($connection->requested_by === auth()->id())<span class="muted">Request sent</span><x-social.button variant="secondary" name="action" value="cancel">Cancel request</x-social.button>
            @else<span class="badge">Wants to be friends</span><x-social.button name="action" value="accept">Accept</x-social.button><x-social.button variant="secondary" name="action" value="decline">Decline</x-social.button>@endif
        </form>
    </article>
@empty<section class="empty panel"><h2>{{ $query ? 'No matching username' : 'Your first friend starts here' }}</h2><p>{{ $query ? 'Check the spelling and try the exact username.' : 'Search above, or browse a public collection to meet its owner.' }}</p><a href="{{ route('collections') }}">Browse public collections</a></section>@endforelse
</div>
{{ $people->links() }}
@endsection
