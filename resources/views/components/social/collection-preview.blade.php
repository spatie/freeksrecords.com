@props(['collector'])

<article class="collection-preview">
    <a href="{{ route('profile', $collector->handle) }}" class="collection-preview-link" aria-label="Browse {{ $collector->name }}’s collection">
        <div class="collection-preview-covers" aria-hidden="true">
            @foreach($collector->records->take(4) as $record)
                <img src="{{ $record->payload['cover'] }}" alt="" loading="lazy" width="180" height="180">
            @endforeach
        </div>
        <div class="collection-preview-caption"><h2>{{ $collector->name }}</h2><p>{{ '@'.$collector->handle }} <span>{{ number_format($collector->records_count) }} {{ Str::plural('record', $collector->records_count) }}</span></p></div>
    </a>
</article>
