@extends('social.layout')
@section('title', 'Your friends')
@section('content')
<p class="eyebrow">Good records. Good company.</p><h1>Your listening circle<span>.</span></h1>
<p class="lede">A friendship starts with a request and an acceptance.</p>
<form class="search" method="get" action="/friends"><label for="find-handle">Find someone by profile handle</label><div class="actions"><input id="find-handle" name="q" value="{{ $query }}" maxlength="30" placeholder="e.g. robin"><button>Search ↗</button><a href="/friends">My connections</a></div></form>
<div class="people-list">
@forelse($people as $person)
    @php($connection = $connections->first(fn ($f) => in_array($person->id, [$f->user_low, $f->user_high], true)))
    <article class="person-row panel">
        <div><h2><a href="{{ route('profile', $person->handle) }}">{{ $person->name }}</a></h2><p>{{ '@'.$person->handle }}</p></div>
        <form class="actions" method="post" action="{{ route('friend.action', $person) }}">@csrf
            @if(!$connection)<button name="action" value="request">Send friend request</button>
            @elseif($connection->accepted_at)<span class="badge">Friends</span><button class="secondary" name="action" value="unfriend">Unfriend</button>
            @elseif($connection->requested_by === auth()->id())<span class="muted">Request sent</span><button class="secondary" name="action" value="cancel">Cancel request</button>
            @else<span class="badge">Wants to be friends</span><button name="action" value="accept">Accept</button><button class="secondary" name="action" value="decline">Decline</button>@endif
        </form>
    </article>
@empty<section class="empty panel"><h2>{{ $query ? 'No matching handles.' : 'Make room for your people.' }}</h2><p>{{ $query ? 'Try another profile handle.' : 'Search for a friend’s handle above to send your first request.' }}</p></section>@endforelse
</div>
{{ $people->links() }}
@endsection
