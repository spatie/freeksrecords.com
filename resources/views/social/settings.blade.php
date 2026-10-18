@extends('social.layout')
@section('title', 'Your preferences')
@section('content')
@php($user = auth()->user())
<p class="eyebrow">Make yourself at home</p><h1>Your preferences<span>.</span></h1>
<div class="two-columns">
    <section class="panel">
        <h2>Profile & privacy</h2>
        <form class="stack" method="post" action="/settings">
            @csrf @method('patch')
            <label>Display name<input name="name" value="{{ old('name', $user->name) }}" maxlength="80" required></label>
            <label>Profile handle<input name="handle" value="{{ old('handle', $user->handle) }}" pattern="[a-zA-Z0-9_]{3,30}" required></label>
            <label>Who can see your collection?
                <select name="visibility">
                    @foreach(['only_me' => 'Only me', 'friends' => 'Friends', 'everyone' => 'Everyone'] as $value => $label)
                        @if(!$user->legacy_owner || $value === 'everyone')<option value="{{ $value }}" @selected(old('visibility', $user->visibility) === $value)>{{ $label }}</option>@endif
                    @endforeach
                </select>
                <small>{{ $user->legacy_owner ? 'Freek’s original collection stays public to preserve existing links and previews.' : 'Friends means accepted mutual friends. Everyone includes visitors who are not signed in. Private profiles show only your name and handle.' }}</small>
            </label>
            <input type="hidden" name="comments_enabled" value="0">
            <label class="check"><input type="checkbox" name="comments_enabled" value="1" @checked(old('comments_enabled', $user->comments_enabled))>Allow comments on my records</label>
            <small>Switching this off stops new comments. Existing comments remain visible and can be deleted.</small>
            <input type="hidden" name="digest_enabled" value="0">
            <label class="check"><input type="checkbox" name="digest_enabled" value="1" @checked(old('digest_enabled', $user->digest_enabled))>Send my weekly friends digest</label>
            <small>Mondays at 09:00 Europe/Brussels. No new records, no email. Current friendship and privacy always apply.</small>
            <button>Save preferences</button>
        </form>
    </section>
    <section class="panel">
        <p class="eyebrow">Connected collection</p><h2>Discogs</h2>
        @if($user->discogs_token)
            <p>Connected as <strong>{{ $user->discogs_username }}</strong>.</p>
            <p>{{ $user->sync_status ?? 'Ready to import' }}<br><small>Last successful sync: {{ $user->last_synced_at?->timezone('Europe/Brussels')->format('j M Y, H:i') ?? 'Not yet' }}</small></p>
            <form method="post" action="/discogs/sync">@csrf<button>Sync collection now ↻</button></form>
            <form class="spaced" method="post" action="/discogs">@csrf @method('delete')<button class="secondary">Disconnect Discogs</button></form>
        @else
            <p>Authorize Freek’s Records on Discogs to import your collection. We never ask for your Discogs password.</p>
            <form method="post" action="/discogs/connect">@csrf<button>{{ $user->discogs_username ? 'Reconnect Discogs' : 'Connect Discogs' }} ↗</button></form>
        @endif
        <p class="muted">Syncs run daily and mirror removals. Historical records in your first import never appear as new additions. You can reconnect the same Discogs account without losing comments.</p>
        @if(app()->environment('local') && config('services.social_demo'))
            <hr class="spaced">
            <h2 class="spaced">Preview tools</h2>
            <p>Enable the weekly digest and save your preferences first. This local-only check uses the last seven days and does not change your weekly send checkpoint.</p>
            <form method="post" action="/demo/digest">@csrf<button class="secondary">Check my digest preview</button></form>
            <p class="muted spaced">Open the “Freeks Records preview inbox” portal to read captured emails. No messages leave the orb.</p>
        @endif
    </section>
</div>
@endsection
