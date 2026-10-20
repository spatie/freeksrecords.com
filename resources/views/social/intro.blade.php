@extends('social.layout')
@section('title', 'A shelf for every record')
@section('body-class', 'home-page')
@section('content')
<section class="landing-hero">
    <div class="landing-copy">
        <h1>A shelf for every record.</h1>
        <p class="lede">Keep collecting on Discogs. Sync your collection here and choose who gets to see it.</p>
        <div class="landing-actions"><x-social.button href="/register">Create account</x-social.button><a href="{{ route('collections') }}">Browse collections</a></div>
        <p class="landing-note">Private until you choose to share.<br>Already a member? <a href="/login">Sign in</a></p>
    </div>
    <div class="hero-artwork" data-artwork-state="paused">
        <div class="record-wall" aria-hidden="true">
            <div class="record-wall-columns">
                @foreach($heroCovers->chunk(8) as $covers)
                    <div class="record-wall-column">
                        <div class="record-wall-track">
                            @foreach(range(1, 2) as $copy)
                                <div class="record-wall-group">
                                    @foreach($covers as $cover)
                                        <img src="{{ $cover }}" alt="" width="180" height="180">
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <button class="artwork-toggle" type="button" hidden>Pause artwork</button>
    </div>
</section>
<section class="public-collections" aria-labelledby="collections-title">
    <div class="section-heading"><div><h2 id="collections-title">Open collections</h2><p>Have a look through the shelves people have chosen to share.</p></div><a href="{{ route('collections') }}">All public collections</a></div>
    <div class="collection-previews">
        @forelse($collectors as $collector)
            <x-social.collection-preview :collector="$collector" />
        @empty
            <p class="muted">The shelves are still filling up. Yours could be the first.</p>
        @endforelse
    </div>
</section>
@endsection

@push('scripts')
<script>
    (() => {
        const artwork = document.querySelector('.hero-artwork');
        const toggle = artwork.querySelector('.artwork-toggle');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let paused = false;

        const update = () => {
            artwork.dataset.artworkState = paused || reducedMotion.matches ? 'paused' : 'running';
            toggle.textContent = paused ? 'Resume artwork' : 'Pause artwork';
            toggle.hidden = reducedMotion.matches;
        };

        toggle.addEventListener('click', () => {
            paused = !paused;
            update();
        });
        reducedMotion.addEventListener('change', update);
        update();
    })();
</script>
@endpush
