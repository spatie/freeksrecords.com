<div data-unread-count="{{ $unreadCount }}">
@if($unreadCount)
    <form class="notifications-read" method="post" action="/notifications/read">@csrf<x-social.button variant="text">Mark all as read</x-social.button></form>
@endif
<div class="notification-list">
@forelse($notifications as $notification)
    <article class="notification-item {{ $notification->read_at ? 'read' : 'unread' }}">
        <a href="{{ $notification->record ? route('shelf.record', $notification->record) : route('friends') }}">
            @if($notification->record)
                <img src="{{ $notification->record->payload['cover'] }}" alt="" width="48" height="48" loading="lazy">
            @else
                <span class="notification-avatar" aria-hidden="true">{{ Str::upper(Str::substr($notification->actor->name, 0, 1)) }}</span>
            @endif
            <span class="notification-copy">
                <span><strong>{{ $notification->actor->name }}</strong> {{ match($notification->kind) { 'friend_request' => 'sent you a friend request.', 'friend_accepted' => 'accepted your friend request.', 'appreciation' => 'appreciated your record.', 'comment' => 'commented on your record.' } }}</span>
                @if($notification->record)<span class="muted">{{ $notification->record->payload['displayTitle'] }}</span>@endif
                @unless($notification->read_at)<span class="notification-unread">Unread</span>@endunless
            </span>
        </a>
    </article>
@empty<p class="notifications-empty">All quiet for now. Friend requests, appreciations, and comments will arrive here.</p>@endforelse
</div>
@if($notifications->hasPages())<div class="notification-pagination">{{ $notifications->links() }}</div>@endif
</div>
