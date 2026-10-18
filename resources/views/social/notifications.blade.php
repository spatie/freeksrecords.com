@extends('social.layout')
@section('title', 'Notifications')
@section('content')
<p class="eyebrow">From the circle</p><div class="section-heading"><h1>A little conversation<span>.</span></h1><form method="post" action="/notifications/read">@csrf<x-social.button variant="secondary">Mark all as read</x-social.button></form></div>
<div class="people-list">
@forelse($notifications as $notification)
    <article class="panel notification {{ $notification->read_at ? 'read' : '' }}">
        <span class="badge">{{ $notification->read_at ? 'Read' : 'New' }}</span>
        <p><a href="{{ route('profile', $notification->actor->handle) }}">{{ $notification->actor->name }}</a>
        {{ match($notification->kind) { 'friend_request' => 'sent you a friend request.', 'friend_accepted' => 'accepted your friend request.', 'appreciation' => 'appreciated your record.', 'comment' => 'left a sleeve note on your record.' } }}</p>
        @if($notification->record)<a href="{{ route('shelf.record', $notification->record) }}">{{ $notification->record->payload['displayTitle'] }}</a>@else<a href="/friends">View connections</a>@endif
    </article>
@empty<section class="empty panel"><h2>All quiet for now.</h2><p>Friend requests, appreciations, and sleeve notes will arrive here.</p></section>@endforelse
</div>
{{ $notifications->links() }}
@endsection
