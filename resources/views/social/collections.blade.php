@extends('social.layout')
@section('title', 'Browse collections')
@section('content')
<x-social.page-heading title="Browse collections" description="A look inside the collections people have made public. Find a familiar favourite, or something you’ve never heard." />
<div class="collection-previews">
    @forelse($collectors as $collector)
        <x-social.collection-preview :collector="$collector" />
    @empty
        <section class="empty panel"><h2>No public collections yet</h2><p>Collections are private by default. They’ll appear here when their owners choose Everyone.</p><a href="{{ auth()->check() ? route('settings') : route('register') }}">{{ auth()->check() ? 'Manage my collection’s visibility' : 'Start your collection' }}</a></section>
    @endforelse
</div>
{{ $collectors->links() }}
@endsection
