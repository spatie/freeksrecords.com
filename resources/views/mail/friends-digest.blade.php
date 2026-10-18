<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>This week on your friends’ shelves</title></head><body>
<h1>This week on your friends’ shelves</h1>
<p>A few new records from your listening circle, newest first.</p>
<ul>@foreach($records as $record)<li><strong>{{ $record->user->name }}</strong> added <a href="{{ route('shelf.record', $record) }}">{{ $record->payload['displayTitle'] }} — {{ $record->payload['artist'] }}</a> ({{ $record->added_at->timezone('Europe/Brussels')->format('j M') }}).</li>@endforeach</ul>
<p>Only records shared with you at send time are included. Collection access can change later.</p>
<p><a href="{{ route('settings') }}">Turn off the weekly digest in Settings</a> · Freek’s Records</p>
</body></html>
