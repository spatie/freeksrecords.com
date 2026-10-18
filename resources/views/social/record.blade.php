@extends('social.layout')
@section('title', $record->payload['displayTitle'])
@section('content')
@php($payload = $record->payload)
<p class="eyebrow"><a href="{{ route('profile', $record->user->handle) }}">← {{ $record->user->name }}’s shelf</a></p>
<div class="record-detail two-columns">
    <img class="large-cover" src="{{ $payload['cover'] }}" alt="{{ $payload['displayTitle'] }} sleeve" width="600" height="600">
    <section>
        <p class="eyebrow">{{ $payload['artist'] }}</p><h1 class="record-title">{{ $payload['displayTitle'] }}<span>.</span></h1>
        <p class="lede">{{ $payload['label'] }} · {{ $payload['originalYear'] ?? $payload['year'] }}</p>
        <p>Added {{ $record->added_at->timezone('Europe/Brussels')->format('j F Y') }}</p>
        <div class="actions"><a class="button secondary" href="{{ $payload['spotifyUrl'] }}" rel="noopener noreferrer" target="_blank">Spotify ↗</a><a class="button secondary" href="{{ $payload['appleMusicUrl'] }}" rel="noopener noreferrer" target="_blank">Apple Music ↗</a></div>
        <div class="appreciation spaced"><p>{{ $appreciations }} {{ $appreciations === 1 ? 'appreciation' : 'appreciations' }}</p>
            @auth<form method="post" action="/shelves/{{ $record->id }}/appreciations">@csrf @if($appreciated) @method('delete') @endif<button class="{{ $appreciated ? 'secondary' : '' }}">{{ $appreciated ? '♥ Appreciated · Undo' : '♡ Appreciate this record' }}</button></form>@else<a href="/login">Sign in to appreciate or comment</a>@endauth
        </div>
        @if(count($payload['tracks']))<h2 class="spaced">On the record</h2><ol class="tracklist">@foreach($payload['tracks'] as $track)<li><span>{{ $track['position'] }}</span><a href="{{ $track['spotifyUrl'] }}" rel="noopener noreferrer" target="_blank">{{ $track['title'] }}</a><small>{{ $track['duration'] }}</small></li>@endforeach</ol>@endif
    </section>
</div>
<section class="conversation panel">
    <div class="section-heading"><h2>Sleeve notes</h2>@if(auth()->id() === $record->user_id)<a href="/settings">Comment controls ↗</a>@endif</div>
    <p class="muted">A conversation about the record. Keep it kind.</p>
    @foreach($comments as $comment)
        <article class="comment"><div class="section-heading"><strong><a href="{{ route('profile', $comment->user->handle) }}">{{ $comment->user->name }}</a></strong>
            @if(auth()->check() && in_array(auth()->id(), [$comment->user_id, $record->user_id], true))<form method="post" action="/shelves/{{ $record->id }}/comments/{{ $comment->id }}">@csrf @method('delete')<button class="text-button" aria-label="Delete comment by {{ $comment->user->name }}">Delete</button></form>@endif
        </div><p class="comment-body">{{ $comment->body }}</p></article>
    @endforeach
    {{ $comments->links() }}
    @if(!$record->user->comments_enabled)<p>New comments are turned off by the collection owner.</p>
    @elseif(auth()->check())<form class="stack" method="post" action="/shelves/{{ $record->id }}/comments">@csrf<label>Add a sleeve note<textarea name="body" rows="3" maxlength="2000" required>{{ old('body') }}</textarea></label><button>Post comment ↗</button></form>
    @else<p><a href="/login">Sign in</a> to join the conversation.</p>@endif
</section>
@endsection
