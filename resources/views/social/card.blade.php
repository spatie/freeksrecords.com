<article class="record-card">
    <a class="cover-link" href="{{ route('shelf.record', $record) }}"><img src="{{ $record->payload['cover'] }}" alt="{{ $record->payload['displayTitle'] }} sleeve" loading="lazy" width="360" height="360"></a>
    <div class="card-body">
        @if($showOwner ?? false)<p class="eyebrow"><a href="{{ route('profile', $record->user->handle) }}">{{ $record->user->name }}</a> added</p>@endif
        <h2><a href="{{ route('shelf.record', $record) }}">{{ $record->payload['displayTitle'] }}</a></h2>
        <p>{{ $record->payload['artist'] }}</p>
        <small>Added <time datetime="{{ $record->added_at->toIso8601String() }}">{{ $record->added_at->timezone('Europe/Brussels')->format('j M Y') }}</time></small>
    </div>
</article>
