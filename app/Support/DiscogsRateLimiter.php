<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

class DiscogsRateLimiter
{
    /** @param Closure(): Response $request */
    public function request(Closure $request, bool $authenticated = true): Response
    {
        $waited = 0;

        while (true) {
            // Serialize requests as well as reservations so a response can stop all workers before the next request.
            $result = Cache::lock('discogs-request-lock', 35)->get(function () use ($request, $authenticated): Response|int {
                $state = Cache::get('discogs-request-budget', []);
                $now = now()->getTimestampMs();
                $delay = ($state['next'] ?? 0) - $now;
                if ($delay > 0) {
                    return $delay;
                }

                $interval = max($authenticated ? 1100 : 2500, $state['interval'] ?? 0);
                Cache::put('discogs-request-budget', ['next' => $now + $interval, 'interval' => $interval], 120);
                $response = $request();

                $limit = $this->number($response, 'X-Discogs-Ratelimit');
                if ($limit !== null && $limit > 0) {
                    $interval = max($interval, (int) ceil(60000 / $limit));
                }
                $remaining = $this->number($response, 'X-Discogs-Ratelimit-Remaining');
                $used = $this->number($response, 'X-Discogs-Ratelimit-Used');
                if ($limit !== null && $used !== null) {
                    $remaining = min($remaining ?? $limit, max(0, $limit - $used));
                }
                $delay = $remaining === 0 ? max($interval, 60000) : $interval;
                if ($response->status() === 429) {
                    $delay = max($delay, 60000);
                }
                if (in_array($response->status(), [429, 503], true)) {
                    $retryAfter = $response->header('Retry-After');
                    $seconds = is_numeric($retryAfter) ? (float) $retryAfter : max(0, (strtotime($retryAfter) ?: 0) - now()->getTimestamp());
                    $delay = max($delay, (int) ceil($seconds * 1000));
                }
                Cache::put('discogs-request-budget', ['next' => now()->getTimestampMs() + $delay, 'interval' => $interval], (int) ceil($delay / 1000) + 120);

                if ($response->status() === 429) {
                    throw new DiscogsRateLimitException((int) ceil($delay / 1000));
                }

                return $response;
            });

            if ($result === false) {
                throw new DiscogsRateLimitException(1);
            }
            if ($result instanceof Response) {
                return $result->throw();
            }
            if ($waited + $result > 5000) {
                throw new DiscogsRateLimitException((int) ceil($result / 1000));
            }

            Sleep::for($result)->milliseconds();
            $waited += $result;
        }
    }

    protected function number(Response $response, string $header): ?int
    {
        $value = $response->header($header);

        return ctype_digit($value) ? (int) $value : null;
    }
}
