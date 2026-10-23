<?php

namespace App\Actions\Discogs;

use App\Actions\Collections\StoreReleaseAction;
use App\Models\CollectionRecord;
use App\Support\DiscogsRecordMapper;

class RefreshRecordFormatsAction
{
    public function __construct(
        protected DiscogsRecordMapper $mapper,
        protected StoreReleaseAction $storeRelease,
    ) {}

    /** @param array<string, mixed> $basic */
    public function execute(CollectionRecord $record, array $basic): bool
    {
        if (! array_key_exists('formats', $basic)) {
            return false;
        }
        foreach ($basic['formats'] as $format) {
            if (empty($format['name'])) {
                return false;
            }
        }
        $release = $record->release;
        $metadata = $this->mapper->formatMetadata($basic);
        $current = $release->recordData();
        if (array_replace($current, $metadata) === $current) {
            return false;
        }
        $record->setRelation('release', $this->storeRelease->execute($release->id, $metadata, replace: true));

        return true;
    }
}
