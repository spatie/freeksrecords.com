This week on your friends’ shelves · Plaat Club

A few new records from your listening circle, newest first.

@foreach($records as $record)
{!! $record->user->name !!} added {!! $record->payload['displayTitle'] !!} — {!! $record->payload['artist'] !!} ({{ $record->added_at->timezone('Europe/Brussels')->format('j M') }}).
{{ route('shelf.record', $record) }}

@endforeach
See friends’ additions: {{ route('feed') }}

Only records shared with you at send time are included. Collection access can change later.
Turn off the weekly digest in Settings: {{ route('settings') }}

Good records. Good company.
