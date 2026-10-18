@extends('social.layout')
@section('title', $person->name.'’s shelf')
@section('content')
<p class="eyebrow">{{ '@'.$person->handle }}</p>
<div class="section-heading"><h1>{{ $person->name }}’s shelf<span>.</span></h1>
    @if(auth()->id() === $person->id)<a href="/settings">Manage your shelf ↗</a>@elseif(auth()->check())<a href="{{ route('friends', ['q' => $person->handle]) }}">Manage friendship ↗</a>@endif
</div>
@if(!$visible)
    <section class="empty panel"><h2>A private shelf.</h2><p>This collection isn’t shared with you. Only the collector’s name and handle are public.</p></section>
@else
    <p class="lede">{{ $records->total() }} records, one at a time.</p>
    @if($person->legacy_owner)<p><a href="/">Browse Freek’s original record room ↗</a></p>@endif
    <div class="record-grid">@forelse($records as $record)@include('social.card')@empty<section class="empty panel"><h2>A shelf waiting for its first record.</h2><p>{{ auth()->id() === $person->id ? 'Connect Discogs in Settings to bring your collection home.' : 'Nothing here yet. Check back after the first import.' }}</p></section>@endforelse</div>
    {{ $records->links() }}
@endif
@endsection
