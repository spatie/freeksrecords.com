@extends('social.layout')
@section('title', 'Your collection. Good company.')
@section('content')
<section class="landing-hero">
    <div class="landing-copy">
        <p class="eyebrow">A home for your music</p>
        <h1>Your collection.<br>Good company<span>.</span></h1>
        <p class="lede">Bring your vinyl, CDs and cassettes together. Share with friends, on your terms.</p>
        <div class="landing-actions"><x-social.button href="/register">Start your collection</x-social.button><x-social.button variant="secondary" href="#how-it-works">See how it works</x-social.button></div>
    </div>
    <div class="format-still-life" role="img" aria-label="An illustrated vinyl sleeve and record, CD case and cassette">
        <div class="demo-vinyl"><div class="vinyl-disc"></div><img src="/images/demo-sleeve-1.svg" alt="" width="400" height="400"></div>
        <div class="demo-cd"><img src="/images/demo-sleeve-2.svg" alt="" width="240" height="240"><span class="cd-disc"></span></div>
        <div class="demo-cassette"><span>GOOD COMPANY / MIX TAPE</span><div class="cassette-window"><i></i><i></i></div><small>SIDE A · C60</small></div>
    </div>
</section>
<section id="how-it-works" class="landing-steps" aria-label="How it works">
    <div><p class="eyebrow">01 / Bring your music</p><h2>Connect Discogs</h2><p>Import your collection and keep it in sync.</p></div>
    <div><p class="eyebrow">02 / On your terms</p><h2>Choose who sees it</h2><p>Your collection is private by default. Share with friends when you’re ready.</p></div>
    <div><p class="eyebrow">03 / Good company</p><h2>Find your friends</h2><p>See what your friends are adding and start a conversation. Newest first, no ranked feed.</p></div>
</section>
<p class="original-collection"><a href="/collection">Explore Freek’s original collection</a></p>
@endsection
