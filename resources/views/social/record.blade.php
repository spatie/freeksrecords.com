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
        @if($record->formats())<p>Format: {{ implode(' / ', $record->formats()) }}</p>@endif
        <p>Added {{ $record->added_at->timezone('Europe/Brussels')->format('j F Y') }}</p>
        <div class="actions"><a class="button secondary" href="{{ $payload['spotifyUrl'] }}" rel="noopener noreferrer" target="_blank">Spotify ↗</a><a class="button secondary" href="{{ $payload['appleMusicUrl'] }}" rel="noopener noreferrer" target="_blank">Apple Music ↗</a></div>
        @if(auth()->id() === $record->user_id)
            <section class="panel spaced">
                <h2>Owner notes</h2>
                <form class="stack" method="post" action="{{ route('shelf.notes.update', $record) }}">
                    @csrf @method('patch')
                    <label>Shared note<textarea name="shared_note" rows="4" maxlength="5000">{{ old('shared_note', $record->shared_note) }}</textarea></label>
                    <small>Visible only to people who can see your collection. Sharing a note never makes a private record public.</small>
                    <label>Private note<textarea name="private_note" rows="4" maxlength="5000">{{ old('private_note', $record->private_note) }}</textarea></label>
                    <small>Only you can see this. Notes belong to this copy, stay through syncs, and are separate from visitor comments. Clear either field to remove its note.</small>
                    <button>Save owner notes</button>
                </form>
            </section>
        @elseif(filled($record->shared_note))
            <section class="panel spaced">
                <h2>Owner’s shared note</h2>
                <p class="comment-body">{{ $record->shared_note }}</p>
            </section>
        @endif
        <div class="appreciation spaced"><p>{{ $appreciations }} {{ $appreciations === 1 ? 'appreciation' : 'appreciations' }}</p>
            @auth<form method="post" action="/shelves/{{ $record->id }}/appreciations">@csrf @if($appreciated) @method('delete') @endif<button class="{{ $appreciated ? 'secondary' : '' }}">{{ $appreciated ? '♥ Appreciated · Undo' : '♡ Appreciate this record' }}</button></form>@else<a href="/login">Sign in to appreciate or comment</a>@endauth
        </div>
        @if(count($payload['tracks']))<h2 class="spaced">On the record</h2><ol class="tracklist">@foreach($payload['tracks'] as $track)<li><span>{{ $track['position'] }}</span><a href="{{ $track['spotifyUrl'] }}" rel="noopener noreferrer" target="_blank">{{ $track['title'] }}</a><small>{{ $track['duration'] }}</small></li>@endforeach</ol>@endif
    </section>
</div>
<section class="conversation panel">
    <div class="section-heading"><h2>Comments</h2>@if(auth()->id() === $record->user_id)<a href="/settings">Comment controls ↗</a>@endif</div>
    <p class="muted">A conversation about the record. Keep it kind.</p>
    @foreach($comments as $comment)
        <article class="comment"><div class="section-heading"><strong><a href="{{ route('profile', $comment->user->handle) }}">{{ $comment->user->name }}</a></strong>
            @if(auth()->check() && in_array(auth()->id(), [$comment->user_id, $record->user_id], true))<form method="post" action="/shelves/{{ $record->id }}/comments/{{ $comment->id }}">@csrf @method('delete')<button class="text-button" aria-label="Delete comment by {{ $comment->user->name }}">Delete</button></form>@endif
        </div><p class="comment-body">{{ $comment->body }}</p></article>
    @endforeach
    {{ $comments->links() }}
    @if(!$record->user->comments_enabled)<p>New comments are turned off by the collection owner.</p>
    @elseif(auth()->check())<form class="stack" method="post" action="/shelves/{{ $record->id }}/comments">@csrf<label>Add a comment<textarea name="body" rows="3" maxlength="2000" required>{{ old('body') }}</textarea></label><button>Post comment ↗</button></form>
    @else<p><a href="/login">Sign in</a> to join the conversation.</p>@endif
</section>
@endsection
