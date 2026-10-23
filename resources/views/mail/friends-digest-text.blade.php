Sunday Morning · Plaat Club

@foreach($records as $record)
{!! $record->user->name !!} added {!! $record->payload['displayTitle'] !!} — {!! $record->payload['artist'] !!} ({{ $record->added_at->timezone('Europe/Brussels')->format('j M') }}).
{{ $record->url() }}

@endforeach
See friends’ additions: {{ route('feed') }}

Turn off the weekly digest in Settings: {{ route('settings') }}
