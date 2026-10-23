@php
    $status = (int) trim($__env->yieldContent('code', '500'));
    [$heading, $description] = match ($status) {
        401 => ['Join the club.', 'Sign in to pick up where you left off.'],
        403 => ['Not your side.', 'This page isn’t available to you. Some collections are just for friends—or only their owner.'],
        404 => ['This one skipped.', 'We couldn’t find the page you’re looking for. The link may be old, or the collection may no longer be available.'],
        419 => ['Let’s start again.', 'Your session has expired. Head back to the club and try again.'],
        429 => ['One beat at a time.', 'That was a few too many requests. Take a moment, then try again.'],
        503 => ['Changing the record.', 'We’re making a few improvements to the club. Your collection is safe. We’ll be back soon.'],
        default => ['A little off track.', 'Something isn’t playing quite right on our side. Please try again in a little while.'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#f5442a">
        <title>{{ $status }} · {{ $heading }} — Plaat Club</title>
        <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
        <style>
            @font-face {
                font-family: 'Club Display';
                src: url('/fonts/barlow-condensed-bold.ttf') format('truetype');
                font-weight: 700;
                font-display: swap;
            }
            @font-face {
                font-family: 'Club Sans';
                src: url('/fonts/dm-sans-regular.ttf') format('truetype');
                font-weight: 400;
                font-display: swap;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100svh;
                background: #f5442a;
                color: #1e1e15;
                font: 18px/1.6 'Club Sans', sans-serif;
            }
            a { color: inherit; text-underline-offset: 6px; }
            a:focus-visible { outline: 2px solid currentColor; outline-offset: 6px; }
            header { padding: 28px 4.5vw; }
            .brand {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                font: 700 36px/.9 'Club Display', sans-serif;
                letter-spacing: -1.5px;
            }
            .brand::before {
                content: '';
                width: 24px;
                height: 24px;
                border: 9px double #1e1e15;
                border-radius: 50%;
            }
            main {
                display: grid;
                grid-template-columns: 1fr 1fr;
                align-items: center;
                gap: 5vw;
                min-height: calc(100svh - 94px);
                max-width: 1700px;
                margin: auto;
                padding: 50px 6vw 80px;
                overflow: hidden;
                background: linear-gradient(155deg, transparent 65%, #ffb27435 65%);
            }
            .eyebrow { margin: 0 0 24px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; }
            h1 {
                max-width: 8ch;
                margin: 0 0 26px;
                font: 700 clamp(80px, 10.5vw, 170px)/.85 'Club Display', sans-serif;
                letter-spacing: -.025em;
                text-transform: uppercase;
            }
            .description { max-width: 36ch; margin: 0 0 38px; }
            .actions { display: flex; align-items: flex-start; flex-direction: column; gap: 20px; }
            .club-link {
                display: inline-block;
                border: 1px solid;
                border-radius: 50%;
                padding: 16px 30px;
                background: #fff0cf;
                text-decoration: none;
                font: 700 25px/1.2 'Club Display', sans-serif;
                transform: rotate(-5deg);
            }
            .club-link:hover { background: #dbee8d; }
            .public-link { font-size: 15px; padding-block: 8px; }
            .public-link:hover { text-decoration-thickness: 2px; }
            .broken-record { position: relative; aspect-ratio: 1; width: 100%; max-width: 620px; }
            .record-half {
                position: absolute;
                inset: 0;
                border: 2px solid #1e1e15;
                border-radius: 50%;
                background: repeating-radial-gradient(circle, #23231e 0 3px, #38382f 4px, #1e1e19 6px 8px);
                clip-path: polygon(0 0, 55% 0, 49% 19%, 53% 26%, 47% 37%, 51% 46%, 46% 58%, 50% 68%, 44% 79%, 47% 89%, 42% 100%, 0 100%);
                transform: translate(-7px, -8px) rotate(-7deg);
            }
            .record-half + .record-half {
                clip-path: polygon(55% 0, 100% 0, 100% 100%, 42% 100%, 47% 89%, 44% 79%, 50% 68%, 46% 58%, 51% 46%, 47% 37%, 53% 26%, 49% 19%);
                transform: translate(16px, 18px) rotate(8deg);
            }
            .record-label {
                position: absolute;
                inset: 31%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                background: #dbee8d;
                font: 700 clamp(40px, 6vw, 90px)/1 'Club Display', sans-serif;
            }
            .record-label::after { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #1e1e15; margin-top: 8px; }
            .record-label small { font-size: clamp(9px, 1vw, 14px); letter-spacing: .06em; margin-top: 8px; }
            .maintenance main { min-height: calc(100svh - 160px); }
            .maintenance h1 { color: #fff0cf; max-width: 9ch; font-size: clamp(80px, 9vw, 145px); }
            .maintenance .eyebrow { display: inline-block; background: #dbee8d; padding: 5px 10px; }
            .maintenance .description { color: #fff0cf; max-width: 33ch; }
            .maintenance .record-half { clip-path: none; transform: translateX(12%) rotate(12deg); }
            .maintenance .record-label { font-size: clamp(36px, 5vw, 72px); }
            .maintenance .broken-record { width: 88%; }
            .sleeve {
                position: absolute;
                inset: 0 42% 0 -10%;
                display: flex;
                align-items: flex-start;
                padding: 9%;
                background: #fff0cf;
                box-shadow: 5px 10px 0 #1e1e1520;
                transform: rotate(-12deg);
                font: 700 clamp(30px, 5vw, 72px)/.9 'Club Display', sans-serif;
                overflow: hidden;
            }
            .sleeve::after { content: ''; position: absolute; width: 100%; aspect-ratio: 1; border-radius: 50%; background: #f5442a; left: -55%; bottom: -15%; }
            footer { padding: 18px 4.5vw 28px; font-size: 11px; letter-spacing: .1em; text-transform: uppercase; }
            @media (max-width: 700px) {
                header { padding: 24px 6vw; }
                .brand { font-size: 30px; }
                main { grid-template-columns: 1fr; gap: 48px; padding: 30px 8vw 70px; }
                h1 { font-size: clamp(80px, 20vw, 130px); max-width: 9ch; }
                .description { font-size: 16px; margin-bottom: 28px; }
                .broken-record { width: 72%; justify-self: center; }
                .record-label { font-size: 48px; }
                .record-label small { font-size: 9px; }
                .maintenance main { min-height: auto; gap: 20px; padding-bottom: 48px; }
                .maintenance h1 { font-size: clamp(72px, 19vw, 125px); }
                .maintenance .broken-record { width: 70%; }
                .maintenance .record-label { font-size: 30px; }
                footer { padding-inline: 8vw; }
            }
        </style>
    </head>
    <body @class(['maintenance' => $status === 503])>
        <header>
            @if($status === 503)
                <span class="brand">Plaat Club.</span>
            @else
                <a class="brand" href="/">Plaat Club.</a>
            @endif
        </header>
        <main>
            <div>
                <p class="eyebrow">{{ $status === 503 ? 'A short intermission' : $status.' / '.($status === 404 ? 'Side not found' : 'Playback interrupted') }}</p>
                <h1>{{ $heading }}</h1>
                <p class="description">{{ $description }}</p>
                @if($status !== 503)
                    <div class="actions">
                        <a class="club-link" href="/">Back to the club ↗</a>
                        <a class="public-link" href="/collections">Browse public collections</a>
                    </div>
                @endif
            </div>
            <div class="broken-record" aria-hidden="true">
                @if($status === 503)
                    <div class="record-half"><div class="record-label">SIDE B<small>BACK SOON</small></div></div>
                    <div class="sleeve">PLAAT<br>CLUB.</div>
                @else
                    @for($half = 0; $half < 2; $half++)
                        <div class="record-half">
                            <div class="record-label">{{ $status }}<small>PLAAT CLUB · SIDE B</small></div>
                        </div>
                    @endfor
                @endif
            </div>
        </main>
        @if($status === 503)
            <footer>Plaat Club · Good records. Good company.</footer>
        @endif
    </body>
</html>
