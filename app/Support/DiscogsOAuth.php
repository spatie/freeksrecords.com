<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscogsOAuth
{
    /**
     * OAuth 1.0a HMAC-SHA1: access credentials never appear in URLs or browser storage.
     *
     * @param  array<string, string|int>  $extra
     * @param  array<string, mixed>  $query
     */
    public function request(string $method, string $path, ?string $token = null, ?string $secret = null, array $extra = [], array $query = []): Response
    {
        $url = 'https://api.discogs.com/'.$path;
        $oauth = [
            'oauth_consumer_key' => config('services.discogs.consumer_key'),
            'oauth_nonce' => Str::random(32), 'oauth_timestamp' => (string) time(),
            'oauth_signature_method' => 'HMAC-SHA1', 'oauth_version' => '1.0',
            ...$extra,
        ];
        if ($token !== null) {
            $oauth['oauth_token'] = $token;
        }
        $parameters = [...$oauth, ...$query];
        ksort($parameters);
        $base = $method.'&'.rawurlencode($url).'&'.rawurlencode(http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
        $key = rawurlencode(config('services.discogs.consumer_secret')).'&'.rawurlencode($secret ?? '');
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));
        $header = implode(', ', array_map(fn ($key, $value) => rawurlencode($key).'="'.rawurlencode((string) $value).'"', array_keys($oauth), $oauth));

        return app(DiscogsRateLimiter::class)->request(fn () => Http::withUserAgent(config('services.discogs.user_agent'))
            ->withHeaders(['Authorization' => 'OAuth '.$header])
            ->asForm()->connectTimeout(5)->timeout(25)->withoutRedirecting()
            ->send($method, $url, ['query' => $query]));
    }
}
