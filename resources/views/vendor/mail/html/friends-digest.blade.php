@props(['records', 'covers', 'webPreview' => false, 'mailMessage'])
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="digest-masthead" bgcolor="#d84435"><a href="{{ config('app.url') }}">Plaat Club.</a><span class="digest-edition">THE WEEKLY FLIP SIDE ↗</span></td>
</tr>
<tr>
<td class="digest-poster" bgcolor="#d84435">
<h1 class="digest-heading">Fresh on<br />their shelves.</h1>
<p>A few new records from your listening circle.<br />Newest first. Good taste all round.</p>
</td>
</tr>
@foreach($records as $record)
<tr>
<td class="digest-row" bgcolor="{{ $loop->even ? '#d3c0ea' : '#d9ed92' }}" style="background-color: {{ $loop->even ? '#d3c0ea' : '#d9ed92' }};">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="digest-art" width="168" valign="top">
@if(isset($covers[$record->id]))
<a href="{{ $record->url() }}"><img class="digest-cover" src="{{ $webPreview ? 'data:image/jpeg;base64,'.base64_encode($covers[$record->id]) : $mailMessage->embedData($covers[$record->id], 'sleeve-'.$record->id.'.jpg', 'image/jpeg') }}" width="144" height="144" alt="Album cover: {{ $record->payload['displayTitle'] }}" /></a>
@else
<table class="digest-missing" width="144" height="144" cellpadding="0" cellspacing="0" role="presentation"><tr><td align="center" valign="middle">NO SLEEVE.<br />STILL GOOD.</td></tr></table>
@endif
</td>
<td class="digest-details" valign="top">
<p class="digest-owner">{{ $record->user->name }} added ↗</p>
<h2 class="digest-title"><a href="{{ $record->url() }}">{{ $record->payload['displayTitle'] }}</a></h2>
<p class="digest-artist">{{ $record->payload['artist'] }}</p>
<p class="digest-meta">@if($record->formats()){{ implode(' / ', $record->formats()) }} · @endif{{ $record->added_at->timezone('Europe/Brussels')->format('j M') }}</p>
</td>
</tr>
</table>
</td>
</tr>
@endforeach
<tr>
<td class="digest-signoff" bgcolor="#292a22">
<p>GOOD RECORDS.<br />EVEN BETTER COMPANY.</p>
<a class="digest-button" href="{{ route('feed') }}">See friends’ additions ↗</a>
</td>
</tr>
<tr>
<td class="digest-footer" bgcolor="#fff9e8">
<p>Only records shared with you at send time are included. Collection access can change later.</p>
<a href="{{ route('settings') }}">Turn off the weekly digest in Settings</a>
</td>
</tr>
</table>
