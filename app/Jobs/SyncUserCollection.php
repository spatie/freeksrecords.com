<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\Discogs;
use App\Support\DiscogsRateLimitException;
use App\Support\DiscogsRecordMapper;
use App\Support\EdgeCache;
use App\Support\RecordCollection;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Interruptible;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncUserCollection implements Interruptible, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 7500;

    public int $tries = 3;

    public int $maxExceptions = 3;

    protected bool $shouldStop = false;

    public function __construct(public int $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function backoff(): int
    {
        return 120;
    }

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDay();
    }

    public function interrupted(int $signal): void
    {
        $this->shouldStop = true;
    }

    /** @phpstan-impure Shutdown signals can change the flag between requests. */
    protected function releaseIfNeeded(int $imported, float $startedAt): bool
    {
        if (! $this->shouldStop && $imported < 25 && microtime(true) - $startedAt < 60) {
            return false;
        }

        $this->release(1);

        return true;
    }

    public function failed(?Throwable $exception): void
    {
        User::query()->whereKey($this->userId)->update(['sync_status' => 'Sync failed. Reconnect if access was revoked, then retry. Existing records were kept.']);
    }

    public function handle(DiscogsRecordMapper $mapper, RecordCollection $legacy): void
    {
        $lock = Cache::lock('discogs-sync-'.$this->userId, 7500);
        if (! $lock->get()) {
            $this->release(120);

            return;
        }
        $user = User::query()->find($this->userId);
        if (! $user) {
            $lock->release();

            return;
        }
        $changed = false;
        try {
            if (! $user->discogs_token) {
                return;
            }
            $state = $user->discogs_sync_state;
            if ($state === null || $state['startedAt'] < now()->subDay()->timestamp) {
                $state = ['page' => 1, 'seen' => [], 'total' => null, 'pages' => null, 'startedAt' => now()->timestamp];
                $user->sync_total = null;
            }
            $user->update(['sync_status' => 'Syncing', 'discogs_sync_state' => $state]);
            $discogs = new Discogs($user);
            $seen = $state['seen'];
            $page = $state['page'];
            $imported = 0;
            $pagesProcessed = 0;
            $startedAt = microtime(true);
            $initial = $user->collection_imported_at === null;
            do {
                if ($this->releaseIfNeeded($imported, $startedAt)) {
                    return;
                }
                $response = $discogs->get('users/'.rawurlencode($user->discogs_username).'/collection/folders/0/releases', ['page' => $page, 'per_page' => 100]);
                if (! isset($response['releases'], $response['pagination']['pages'], $response['pagination']['items']) || ! is_array($response['releases'])) {
                    $user->update(['discogs_sync_state' => null]);
                    throw new RuntimeException('Incomplete collection response');
                }
                if ($state['total'] !== null && ($state['total'] !== $response['pagination']['items'] || $state['pages'] !== $response['pagination']['pages'])) {
                    $user->update(['discogs_sync_state' => null]);
                    throw new RuntimeException('Collection changed during pagination');
                }
                $state['total'] = $response['pagination']['items'];
                $state['pages'] = $response['pagination']['pages'];
                $user->update(['sync_total' => $state['total'], 'discogs_sync_state' => $state]);
                foreach ($response['releases'] as $entry) {
                    if ($this->releaseIfNeeded($imported, $startedAt)) {
                        return;
                    }
                    $instance = $entry['instance_id'];
                    $seen[] = $instance;
                    if ($known = $user->records()->where('instance_id', $instance)->first()) {
                        $changed = $known->refreshFormats($entry['basic_information'], $mapper) || $changed;

                        continue;
                    }
                    $release = $discogs->get('releases/'.$entry['basic_information']['id']);
                    if ($this->releaseIfNeeded($imported, $startedAt)) {
                        return;
                    }
                    $master = empty($entry['basic_information']['master_id']) ? [] : $discogs->get('masters/'.$entry['basic_information']['master_id']);
                    $payload = $mapper->record($entry, $release, $master);
                    // Artwork is public release data, but private collection membership is never published as a share image.
                    $payload['cover'] = $payload['coverSource'];
                    $user->records()->create([
                        'instance_id' => $instance, 'discogs_release_id' => $payload['id'],
                        'added_at' => CarbonImmutable::parse($payload['addedAt'])->utc()->toDateTimeString(),
                        'payload' => $payload, 'is_activity' => ! $initial,
                    ]);
                    $changed = true;
                    $imported++;
                }
                $page++;
                $pagesProcessed++;
                if ($page <= $state['pages']) {
                    $state['page'] = $page;
                    $state['seen'] = array_values(array_unique($seen));
                    $user->update(['discogs_sync_state' => $state]);
                }
                if ($page <= $state['pages'] && $pagesProcessed >= 5) {
                    $this->release(1);

                    return;
                }
            } while ($page <= $response['pagination']['pages']);

            if (count(array_unique($seen)) !== $response['pagination']['items']) {
                $user->update(['discogs_sync_state' => null]);
                throw new RuntimeException('Incomplete collection response');
            }
            DB::transaction(function () use ($user, $seen, &$changed): void {
                $removed = $user->records()->whereNotIn('instance_id', $seen)->delete();
                $changed = $changed || $removed > 0;
                $user->update(['collection_imported_at' => $user->collection_imported_at ?? now(), 'last_synced_at' => now(), 'sync_status' => 'Up to date', 'discogs_sync_state' => null]);
            });
        } catch (DiscogsRateLimitException $exception) {
            $this->release($exception->retryAfter);
        } catch (Throwable $exception) {
            $user->update(['sync_status' => 'Retrying automatically. Existing records were kept.']);
            // Never serialize HTTP exceptions containing OAuth headers into failed_jobs/logs.
            $reason = $exception instanceof RequestException ? 'HTTP '.$exception->response->status() : class_basename($exception);
            if ($exception instanceof ConnectionException && preg_match('/cURL error (\d+)/', $exception->getMessage(), $matches)) {
                $reason .= ' (cURL error '.$matches[1].')';
            }
            throw new RuntimeException('Discogs sync failed for user '.$this->userId.': '.$reason.'. Retry or reconnect.');
        } finally {
            if ($user->legacy_owner) {
                $legacy->flush();
                if ($changed) {
                    app(EdgeCache::class)->purge();
                }
            }
            $lock->release();
        }
    }
}
