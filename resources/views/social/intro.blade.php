@extends('social.layout')
@section('title', 'A listening circle')
@section('content')
<section class="hero">
    <p class="eyebrow">Freek’s Records / Listening circle</p>
    <h1>Better records.<br>Found together<span>.</span></h1>
    <p class="lede">A small corner of the internet for the records you bring home and the friends you share them with.</p>
    <div class="actions"><a class="button" href="/register">Start your shelf ↗</a><a href="/login">Already here? Sign in</a></div>
</section>
<div class="three-columns">
    <section class="panel"><p class="eyebrow">01 / Your shelf</p><h2>Bring your collection.</h2><p>Connect Discogs once. Your collection stays in sync, with privacy set to Only me until you choose otherwise.</p></section>
    <section class="panel"><p class="eyebrow">02 / Your people</p><h2>A mutual connection.</h2><p>Find friends by handle, send a request, and start sharing after they accept. No followers, no ranked feed.</p></section>
    <section class="panel"><p class="eyebrow">03 / A little discovery</p><h2>See what came home.</h2><p>New additions, appreciations, and conversations. A weekly email when there’s something to share. Silence when there isn’t.</p></section>
</div>
@endsection
