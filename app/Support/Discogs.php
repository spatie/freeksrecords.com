<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class Discogs
{
    public function __construct(protected ?User $user = null) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        if ($this->user !== null && $query === [] && preg_match('#^(releases|masters)/[0-9]+$#', $path)) {
            return Cache::remember('discogs-public-v1:'.$path, now()->addWeek(), fn () => $this->fetch($path, $query));
        }

        return $this->fetch($path, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function fetch(string $path, array $query): array
    {
        if ($this->user !== null) {
            for ($attempt = 0; ; $attempt++) {
                try {
                    $response = app(DiscogsOAuth::class)->request('GET', $path, $this->user->discogs_token, $this->user->discogs_secret, query: $query);

                    break;
                } catch (ConnectionException|RequestException $exception) {
                    $status = $exception instanceof RequestException ? $exception->response->status() : null;
                    if ($attempt >= 2 || ($status !== null && $status < 500)) {
                        throw $exception;
                    }
                    Sleep::for(2 ** ($attempt + 1))->seconds();
                }
            }

            return $response->json();
        }
        $url = "https://api.discogs.com/{$path}";

        $response = app(DiscogsRateLimiter::class)->request(
            fn () => $this->request()->get($url, $query),
            authenticated: (bool) config('services.discogs.token'),
        );

        return $response->json();
    }

    protected function request(): PendingRequest
    {
        $request = Http::withUserAgent(config('services.discogs.user_agent'))
            ->connectTimeout(5)
            ->timeout(25);

        $token = config('services.discogs.token');

        if (! $token) {
            return $request;
        }

        return $request->withHeaders(['Authorization' => "Discogs token={$token}"]);
    }
}
