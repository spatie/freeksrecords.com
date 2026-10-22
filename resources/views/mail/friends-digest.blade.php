<x-mail::message>
# Fresh on their shelves.

A few new records from your listening circle, newest first.

@foreach($records as $record)
<x-mail::panel>
<p class="record-owner">{{ $record->user->name }} added</p>
<h2><a href="{{ $record->url() }}">{{ $record->payload['displayTitle'] }}</a></h2>
<p class="record-meta">{{ $record->payload['artist'] }}@if($record->formats()) · {{ implode(' / ', $record->formats()) }}@endif · {{ $record->added_at->timezone('Europe/Brussels')->format('j M') }}</p>
</x-mail::panel>
@endforeach

<x-mail::button :url="route('feed')">
See friends’ additions
</x-mail::button>

Good records. Good company.

<x-slot:subcopy>
Only records shared with you at send time are included. Collection access can change later.

[Turn off the weekly digest in Settings]({{ route('settings') }})
</x-slot:subcopy>
</x-mail::message>
