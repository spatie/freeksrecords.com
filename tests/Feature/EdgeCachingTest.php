<?php

it('lets the edge cache pages without starting a session', function (string $url) {
    $response = $this->get($url);

    $response->assertOk()->assertHeaderMissing('Set-Cookie');

    expect($response->headers->get('Cache-Control'))->toBe('max-age=60, public, s-maxage=86400');
})->with([
    'collection' => ['/collection'],
    'shared record' => ['/record/2189134863/oren-ambarchi-shebang'],
    'record details' => ['/api/records/2189134863'],
]);

it('does not cache inertia visits at the edge', function () {
    $response = $this->get('/collection', ['X-Inertia' => 'true']);

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

it('does not cache missing pages at the edge', function () {
    $response = $this->get('/record/9999999999/unknown-album');

    $response->assertNotFound();
    expect((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

it('does not cache slug redirects at the edge', function () {
    $response = $this->get('/record/2189134863/old-album-title');

    $response->assertStatus(301);
    expect((string) $response->headers->get('Cache-Control'))->not->toContain('s-maxage');
});
