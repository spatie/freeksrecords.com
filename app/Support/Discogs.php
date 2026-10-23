<?php

namespace App\Support;

use App\Models\Artist;
use App\Models\DiscogsResponse;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class Discogs
{
    public function __construct(
        protected ?User $user = null,
        protected ?DiscogsRateLimiter $limiter = null,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        if ($query !== []) {
            return $this->fetch($path, $query);
        }

        if (! preg_match('#^(releases|masters|artists)/([0-9]+)$#', $path, $matches)) {
            return $this->fetch($path, $query);
        }

        $resource = ['resource_type' => $matches[1], 'discogs_id' => (int) $matches[2]];
        $stored = DiscogsResponse::query()->where($resource)->first();

        if ($stored?->expires_at->isFuture()) {
            return $stored->response;
        }

        try {
            $response = $this->fetch($path, []);
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404 || $resource['resource_type'] !== 'artists') {
                throw $exception;
            }

            $response = [];
        }

        DiscogsResponse::query()->updateOrCreate($resource, [
            'response' => $response,
            'fetched_at' => now(),
            'expires_at' => now()->addWeek(),
        ]);

        return $response;
    }

    /**
     * @param  list<array<string, mixed>>  $artists
     * @return list<array{id: int, name: string, profile: string, discogsUrl: string}>
     */
    public function artistBios(array $artists): array
    {
        $bios = [];

        foreach ($artists as $artist) {
            $id = (int) ($artist['id'] ?? 0);
            if ($id === 0 || isset($bios[$id])) {
                continue;
            }

            $stored = Artist::query()->find($id);
            $details = $stored?->profile !== null
                ? ['name' => $stored->name, 'profile' => $stored->profile]
                : $this->get("artists/{$id}");

            $bios[$id] = [
                'id' => $id,
                'name' => app(DiscogsRecordMapper::class)->artistName($details['name'] ?? $artist['name']),
                'profile' => $details['profile'] ?? '',
                'discogsUrl' => "https://www.discogs.com/artist/{$id}",
            ];
        }

        return array_values($bios);
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
                    $response = app(DiscogsOAuth::class)->request(
                        'GET',
                        $path,
                        $this->user->discogs_token,
                        $this->user->discogs_secret,
                        query: $query,
                        limiter: $this->limiter,
                    );

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
