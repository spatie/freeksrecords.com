<?php

namespace App\Actions\Discogs;

use App\Actions\Collections\StoreCollectionRecordAction;
use App\Jobs\EnrichRelease;
use App\Jobs\GenerateRecordCoverPreview;
use App\Models\CollectionRecord;
use App\Models\Release;
use App\Support\Discogs;
use App\Support\DiscogsRecordMapper;
use Carbon\CarbonImmutable;
use Closure;

class ImportDiscogsRecordAction
{
    public function __construct(
        protected FetchDiscogsRecordAction $fetchRecord,
        protected StoreCollectionRecordAction $storeRecord,
        protected RefreshRecordFormatsAction $refreshFormats,
        protected DiscogsRecordMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     * @param  Closure(): bool  $shouldPause
     * @return array{changed: bool, imported: bool}|null
     */
    public function execute(CollectionRecord $record, array $entry, Discogs $discogs, bool $initial, Closure $shouldPause): ?array
    {
        $imported = ! $record->exists;
        $changed = false;
        if ($record->exists) {
            $changed = $this->refreshFormats->execute($record, $entry['basic_information']);
        }

        if ($imported) {
            $attributes = [
                'instance_id' => $entry['instance_id'], 'discogs_release_id' => $entry['basic_information']['id'],
                'added_at' => CarbonImmutable::parse($entry['date_added'])->utc()->toDateTimeString(),
                'is_activity' => ! $initial,
            ];
            if (! Release::query()->where('id', $entry['basic_information']['id'])->exists()) {
                $payload = $this->fetchRecord->execute($entry, $discogs, $shouldPause);
                if ($payload === null) {
                    return null;
                }
                // Collection membership stays private; only release artwork is public.
                $payload['cover'] = $payload['coverSource'];
                $attributes['payload'] = $payload;
            }
            $this->storeRecord->execute($record, $attributes);
            $changed = true;
        }

        if ($record->release->info_imported_at === null || $record->release->musicbrainz_checked_at === null) {
            EnrichRelease::dispatch($record->discogs_release_id)->afterCommit();
        }

        $payload = $record->payload;
        if (empty($payload['coverPreview'])) {
            if (filled($payload['cover'] ?? null) && $payload['cover'] !== $this->mapper->placeholderCover) {
                GenerateRecordCoverPreview::dispatch($record->id)->afterCommit();
            }
        }

        return ['changed' => $changed, 'imported' => $imported];
    }
}
