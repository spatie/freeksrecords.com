@use('App\Filament\Resources\UserResource')

<ul class="admin-friends">
    @forelse($getRecord()->friends()->withCount('records')->orderBy('name')->get() as $friend)
        <li>
            <a class="admin-friend" href="{{ UserResource::getUrl('view', ['record' => $friend]) }}">
                <span>
                    <span class="admin-friend-name">{{ $friend->name }}</span>
                    <span class="admin-friend-handle">{{ '@'.$friend->handle }}</span>
                </span>
                <span class="admin-friend-records">{{ number_format($friend->records_count) }} records</span>
            </a>
        </li>
    @empty
        <li class="text-sm text-gray-500">No accepted friendships yet.</li>
    @endforelse
</ul>
