@extends('social.layout')
@section('title', 'Friends’ additions')
@section('content')
<p class="eyebrow">The listening circle / Latest additions</p>
<div class="section-heading"><h1>Fresh on their shelves<span>.</span></h1><a href="/friends">Find your people ↗</a></div>
<p class="lede">Records your friends brought home. Newest first, always.</p>
<div class="record-grid">
    @forelse($records as $record)@include('social.card', ['showOwner' => true])
    @empty<section class="empty panel"><h2>A quiet week in the circle.</h2><p>New additions will appear when accepted friends share them. Historical first imports stay off the feed.</p><a class="button" href="/friends">Find friends ↗</a></section>@endforelse
</div>
{{ $records->links() }}
@endsection
