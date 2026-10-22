<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserCollection;
use App\Models\CollectionRecord;
use App\Models\User;
use App\Support\Discogs;
use App\Support\DiscogsRateLimitException;
use App\Support\DiscogsRecordMapper;
use App\Support\EdgeCache;
use App\Support\RecordCollection;
use App\Support\RecordCoverImages;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Throwable;

class SyncDiscogsCollectionCommand extends Command
{
    protected $signature = 'records:sync-discogs';

    protected $description = 'Import new Discogs collection additions and remove records no longer in the collection';

    public function handle(
        Discogs $discogs,
        DiscogsRecordMapper $mapper,
        RecordCollection $collection,
        RecordCoverImages $images,
        EdgeCache $edgeCache,
    ): int {
        $owner = User::query()->where('legacy_owner', true)->first();
        if (! $owner) {
            $this->comment('No legacy collection owner; skipping Discogs sync.');

            return self::SUCCESS;
        }
        if ($owner->discogs_token) {
            SyncUserCollection::dispatch($owner->id);

            return self::SUCCESS;
        }
        if (! CollectionRecord::legacy()->exists() && $owner->collection_imported_at === null) {
            $this->call('records:import-snapshot');
        }

        $username = config('services.discogs.username');
        $knownInstanceIds = array_fill_keys(CollectionRecord::legacy()->pluck('instance_id')->all(), true);
        $seenInstanceIds = [];
        $imported = 0;
        $refreshed = 0;
        $failed = 0;
        $page = 1;

        try {
            do {
                $this->info("Checking Discogs collection page {$page}...");

                $response = $discogs->get("users/{$username}/collection/folders/0/releases", [
                    'page' => $page,
                    'per_page' => 100,
                    'sort' => 'added',
                    'sort_order' => 'desc',
                ]);

                foreach ($response['releases'] as $entry) {
                    $seenInstanceIds[] = $entry['instance_id'];

                    if (isset($knownInstanceIds[$entry['instance_id']])) {
                        $known = CollectionRecord::legacy()->where('instance_id', $entry['instance_id'])->first();
                        if ($known?->refreshFormats($entry['basic_information'], $mapper)) {
                            $refreshed++;
                            $collection->flush();
                        }

                        continue;
                    }

                    $this->line("Importing {$entry['basic_information']['title']}...");

                    try {
                        $record = $this->fetchRecord($entry, $discogs, $mapper);
                    } catch (RequestException $exception) {
                        $failed++;

                        $this->warn("Skipped release {$entry['basic_information']['id']}: {$exception->getMessage()}");

                        continue;
                    }

                    $record['cover'] = $this->copyCover($record, $images, $mapper);

                    $collection->store($record);
                    CollectionRecord::legacy()->where('instance_id', $record['instanceId'])->update(['is_activity' => true]);

                    $knownInstanceIds[$entry['instance_id']] = true;
                    $imported++;
                }

                $page++;
            } while ($page <= ($response['pagination']['pages'] ?? 1));
        } catch (ConnectionException|RequestException|DiscogsRateLimitException $exception) {
            $this->error("Discogs sync interrupted. Imported records are saved; rerun to resume. {$exception->getMessage()}");

            $this->purgeEdgeCacheWhenChanged($edgeCache, $imported + $refreshed);

            return self::FAILURE;
        }

        $removed = $this->removeDeletedRecords($collection, $seenInstanceIds, $response['pagination']['items'] ?? null);
        $owner->update(['collection_imported_at' => now(), 'last_synced_at' => now()]);

        $this->call('records:sync-dates');
        $this->call('records:copy-covers');

        $this->comment("Imported {$imported} new records, removed {$removed}, skipped {$failed}.");

        $this->purgeEdgeCacheWhenChanged($edgeCache, $imported + $removed + $refreshed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function fetchRecord(array $entry, Discogs $discogs, DiscogsRecordMapper $mapper): array
    {
        $basic = $entry['basic_information'];

        $release = $discogs->get("releases/{$basic['id']}");

        $master = empty($basic['master_id'])
            ? []
            : $discogs->get("masters/{$basic['master_id']}");

        File::ensureDirectoryExists(storage_path('app/discogs'));
        File::put(storage_path("app/discogs/releases-{$basic['id']}.json"), json_encode($release, JSON_THROW_ON_ERROR));

        return $mapper->record($entry, $release, $master, $discogs->artistBios($release['artists'] ?? $basic['artists']));
    }

    /** @param array<string, mixed> $record */
    protected function copyCover(array $record, RecordCoverImages $images, DiscogsRecordMapper $mapper): string
    {
        if ($record['coverSource'] === $mapper->placeholderCover) {
            return $mapper->placeholderCover;
        }

        try {
            return $images->copy($record['id'], $record['coverSource']);
        } catch (Throwable $exception) {
            $this->warn("Cover copy deferred: {$exception->getMessage()}");

            return $mapper->placeholderCover;
        }
    }

    protected function purgeEdgeCacheWhenChanged(EdgeCache $edgeCache, int $changedRecords): void
    {
        if ($changedRecords === 0) {
            return;
        }

        if (! $edgeCache->isConfigured()) {
            $this->warn('The edge cache is not configured, so cached pages update within a day.');

            return;
        }

        $this->info('Purging the edge cache...');

        $edgeCache->purge()
            ? $this->comment('Purged the edge cache.')
            : $this->warn('The edge cache could not be purged, so cached pages update within a day.');
    }

    /** @param array<int, int> $seenInstanceIds */
    protected function removeDeletedRecords(RecordCollection $collection, array $seenInstanceIds, ?int $expectedCount): int
    {
        if (count($seenInstanceIds) !== $expectedCount) {
            $this->warn('Discogs returned an incomplete collection, so no records were removed.');

            return 0;
        }

        return $collection->removeAllExcept($seenInstanceIds);
    }
}
