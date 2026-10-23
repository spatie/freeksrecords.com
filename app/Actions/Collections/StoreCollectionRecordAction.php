<?php

namespace App\Actions\Collections;

use App\Models\CollectionRecord;
use App\Support\CollectionBrowseIndex;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreCollectionRecordAction
{
    public function __construct(protected StoreReleaseAction $storeRelease) {}

    /** @param array<string, mixed> $attributes */
    public function execute(CollectionRecord $record, array $attributes): CollectionRecord
    {
        return DB::transaction(function () use ($record, $attributes): CollectionRecord {
            $record->fill(Arr::except($attributes, ['payload']));
            $hasPayload = array_key_exists('payload', $attributes);
            if ($hasPayload) {
                $id = (int) ($attributes['payload']['id'] ?? $record->discogs_release_id);
                $release = $this->storeRelease->execute($id, $attributes['payload'], $record->exists && $record->getOriginal('discogs_release_id') === $id);
                $record->discogs_release_id = $release->id;
                $record->setRelation('release', $release);
            } elseif ($record->isDirty('discogs_release_id')) {
                $record->unsetRelation('release');
            }

            if (! $record->exists || $hasPayload || $record->isDirty(['discogs_release_id', 'added_at'])) {
                $record->forceFill(CollectionBrowseIndex::attributes($record->payload, $record->added_at));
            }

            $record->save();

            if ($record->wasRecentlyCreated || $record->wasChanged(['user_id', 'discogs_release_id'])) {
                CollectionBrowseIndex::sync([$record]);
            }

            return $record;
        });
    }
}
