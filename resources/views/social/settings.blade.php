@extends('social.layout')
@section('title', 'Your preferences')
@section('content')
@php($user = auth()->user())
<x-social.page-heading title="Settings" />
<div class="two-columns">
    <section class="panel">
        <h2>Profile & privacy</h2>
        <form class="stack" method="post" action="/settings">
            @csrf @method('patch')
            <x-social.input name="name" label="Display name" :value="$user->name" maxlength="80" required />
            <x-social.input name="handle" label="Username" :value="$user->handle" pattern="[a-zA-Z0-9_]{3,30}" minlength="3" maxlength="30" :hint="'3–30 letters, numbers or underscores. Your collection: plaat.club/'.$user->handle" required />
            <label>Who can see your collection?
                <select name="visibility">
                    @foreach(['only_me' => 'Only me', 'friends' => 'Friends', 'everyone' => 'Everyone'] as $value => $label)
                        @if(!$user->legacy_owner || $value === 'everyone')<option value="{{ $value }}" @selected(old('visibility', $user->visibility) === $value)>{{ $label }}</option>@endif
                    @endforeach
                </select>
                <small>{{ $user->legacy_owner ? 'Freek’s original collection stays public to preserve existing links and previews.' : 'Friends means accepted mutual friends. Everyone includes visitors who are not signed in. Private profiles show only your name and handle.' }}</small>
            </label>
            <x-social.switch name="comments_enabled" :checked="old('comments_enabled', $user->comments_enabled)" description="Switching this off stops new comments. Existing comments remain visible and can be deleted.">Allow comments on my records</x-social.switch>
            <x-social.switch name="digest_enabled" :checked="old('digest_enabled', $user->digest_enabled)" description="Mondays at 09:00 Europe/Brussels. No new records, no email. Current friendship and privacy always apply.">Send my weekly friends digest</x-social.switch>
            <x-social.button>Save preferences</x-social.button>
        </form>
    </section>
    <section class="panel">
        <h2>Discogs</h2>
        @if($user->discogs_token)
            <p>Connected as <strong>{{ $user->discogs_username }}</strong>.</p>
            @if($user->collection_imported_at === null)
                <x-social.import-status :user="$user" :count="$user->records()->count()" />
            @else
                <p>{{ $user->sync_status ?? 'Ready to import' }}<br><small>Last successful sync: {{ $user->last_synced_at?->timezone('Europe/Brussels')->format('j M Y, H:i') ?? 'Not yet' }}</small></p>
                <form method="post" action="/discogs/sync">@csrf<x-social.button>Sync collection now</x-social.button></form>
            @endif
            <form class="spaced" method="post" action="/discogs">@csrf @method('delete')<x-social.button variant="secondary">Disconnect Discogs</x-social.button></form>
        @else
            <p>Connect your Discogs account to import your collection. We never ask for your Discogs password.</p>
            <form method="post" action="/discogs/connect">@csrf<x-social.button>{{ $user->discogs_username ? 'Reconnect Discogs' : 'Connect Discogs' }}</x-social.button></form>
        @endif
        <p class="muted spaced">Syncs run daily and mirror removals. Historical records in your first import never appear as new additions. You can reconnect the same Discogs account without losing comments.</p>
        @if(app()->environment('local') && config('services.social_demo'))
            <hr class="spaced">
            <h2 class="spaced">Preview tools</h2>
            <p>Enable the weekly digest and save your preferences first. This local-only check uses the last seven days and does not change your weekly send checkpoint.</p>
            <form method="post" action="/demo/digest">@csrf<x-social.button variant="secondary">Check my digest preview</x-social.button></form>
            <p class="muted spaced">Open the “Plaat Club preview inbox” portal to read captured emails. No messages leave the orb.</p>
        @endif
    </section>
</div>
@endsection
