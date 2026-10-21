<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\Discogs;
use App\Support\DiscogsRecordMapper;
use App\Support\EdgeCache;
use App\Support\RecordCollection;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Interruptible;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncUserCollection implements Interruptible, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

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
    protected function releaseIfInterrupted(): bool
    {
        if (! $this->shouldStop) {
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
        $user = User::query()->findOrFail($this->userId);
        $changed = false;
        try {
            if (! $user->discogs_token) {
                return;
            }
            $user->update(['sync_status' => 'Syncing', 'sync_total' => null]);
            $discogs = new Discogs($user);
            $seen = [];
            $page = 1;
            $initial = $user->collection_imported_at === null;
            do {
                $response = $discogs->get('users/'.rawurlencode($user->discogs_username).'/collection/folders/0/releases', ['page' => $page, 'per_page' => 100]);
                if (! isset($response['releases'], $response['pagination']['pages'], $response['pagination']['items']) || ! is_array($response['releases'])) {
                    throw new RuntimeException('Incomplete collection response');
                }
                $user->update(['sync_total' => $response['pagination']['items']]);
                foreach ($response['releases'] as $entry) {
                    if ($this->releaseIfInterrupted()) {
                        return;
                    }
                    $instance = $entry['instance_id'];
                    $seen[] = $instance;
                    if ($known = $user->records()->where('instance_id', $instance)->first()) {
                        $changed = $known->refreshFormats($entry['basic_information'], $mapper) || $changed;

                        continue;
                    }
                    $release = $discogs->get('releases/'.$entry['basic_information']['id']);
                    if ($this->releaseIfInterrupted()) {
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
                }
                $page++;
            } while ($page <= $response['pagination']['pages']);

            if (count(array_unique($seen)) !== $response['pagination']['items']) {
                throw new RuntimeException('Incomplete collection response');
            }
            DB::transaction(function () use ($user, $seen, &$changed): void {
                $removed = $user->records()->whereNotIn('instance_id', $seen)->delete();
                $changed = $changed || $removed > 0;
                $user->update(['collection_imported_at' => $user->collection_imported_at ?? now(), 'last_synced_at' => now(), 'sync_status' => 'Up to date']);
            });
        } catch (Throwable $exception) {
            $user->update(['sync_status' => 'Retrying automatically. Existing records were kept.']);
            // Never serialize HTTP exceptions containing OAuth headers into failed_jobs/logs.
            $reason = $exception instanceof RequestException ? 'HTTP '.$exception->response->status() : class_basename($exception);
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
