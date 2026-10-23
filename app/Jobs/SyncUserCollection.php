<?php

namespace App\Jobs;

use App\Actions\Discogs\CompleteCollectionSyncAction;
use App\Actions\Discogs\ImportDiscogsRecordAction;
use App\Models\User;
use App\Support\Discogs;
use App\Support\DiscogsRateLimiter;
use App\Support\DiscogsRateLimitException;
use App\Support\EdgeCache;
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

    public int $uniqueFor = 8 * 24 * 60 * 60;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public bool $full = true;

    protected bool $shouldStop = false;

    public function __construct(public int $userId, bool $full = true)
    {
        $this->full = $full;
    }

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
        return CarbonImmutable::now()->addWeek();
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
        $user = User::query()->find($this->userId);
        if ($user && $user->collection_imported_at === null) {
            activity('discogs')->causedBy($user)->performedOn($user)
                ->event('initial_import_failed')->log('Initial collection import failed');
        }
    }

    public function handle(ImportDiscogsRecordAction $importRecord, CompleteCollectionSyncAction $completeSync): void
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
            $initial = $user->collection_imported_at === null;
            $full = $this->full || $initial
                || $user->last_synced_at === null || $user->last_synced_at->lt(now()->subDay());
            $cutoff = ($user->discogs_additions_checked_at ?? $user->last_synced_at)?->subMinute()->timestamp;
            $state = $user->discogs_sync_state;

            $starting = $state === null || $state['startedAt'] < now()->subWeek()->timestamp || ($full && ! ($state['full'] ?? true));
            if ($starting) {
                $state = [
                    'page' => 1, 'seen' => [], 'total' => null, 'pages' => null, 'startedAt' => now()->timestamp,
                    'full' => $full,
                ];
                $user->sync_total = null;
            }

            $full = $state['full'] ?? true;
            DB::transaction(function () use ($user, $state, $initial, $starting): void {
                $user->update(['sync_status' => 'Syncing', 'discogs_sync_state' => $state]);
                if ($initial && $starting) {
                    activity('discogs')->causedBy($user)->performedOn($user)
                        ->event('initial_import_started')->log('Initial collection import started');
                }
            });
            $discogs = new Discogs($user, new DiscogsRateLimiter(requestLimit: 12));
            $seen = $state['seen'];
            $page = $state['page'];
            $imported = 0;
            $pagesProcessed = 0;
            $startedAt = microtime(true);
            $boundaryReached = false;
            do {
                if ($this->releaseIfNeeded($imported, $startedAt)) {
                    return;
                }
                $query = ['page' => $page, 'per_page' => 100];
                if (! $full) {
                    $query += ['sort' => 'added', 'sort_order' => 'desc'];
                }
                $response = $discogs->get('users/'.rawurlencode($user->discogs_username).'/collection/folders/0/releases', $query);
                if (! isset($response['releases'], $response['pagination']['pages'], $response['pagination']['items']) || ! is_array($response['releases'])) {
                    $user->update(['discogs_sync_state' => null]);
                    throw new RuntimeException('Incomplete collection response');
                }
                if ($response['releases'] === [] && $response['pagination']['items'] > 0) {
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
                $knownRecords = $user->records()->whereIn('instance_id', array_column($response['releases'], 'instance_id'))->get()->keyBy('instance_id');
                foreach ($response['releases'] as $entry) {
                    if ($this->releaseIfNeeded($imported, $startedAt)) {
                        return;
                    }
                    if (! $full && CarbonImmutable::parse($entry['date_added'])->timestamp < $cutoff) {
                        $boundaryReached = true;

                        break;
                    }
                    $instance = $entry['instance_id'];
                    $seen[] = $instance;
                    $result = $importRecord->execute(
                        $knownRecords->get($instance) ?? $user->records()->make(),
                        $entry,
                        $discogs,
                        $initial,
                        fn (): bool => $this->releaseIfNeeded($imported, $startedAt),
                    );
                    if ($result === null) {
                        return;
                    }
                    $changed = $result['changed'] || $changed;
                    $imported += (int) $result['imported'];
                }
                if ($boundaryReached) {
                    break;
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

            if (! $boundaryReached && count(array_unique($seen)) !== $response['pagination']['items']) {
                $user->update(['discogs_sync_state' => null]);
                throw new RuntimeException('Incomplete collection response');
            }
            $changed = $completeSync->execute($user, $seen, $full, CarbonImmutable::createFromTimestamp($state['startedAt'])) || $changed;
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
            if ($user->legacy_owner && $changed) {
                app(EdgeCache::class)->purge();
            }
            $lock->release();
        }
    }
}
