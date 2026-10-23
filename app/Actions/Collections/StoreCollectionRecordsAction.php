<?php

namespace App\Actions\Collections;

use App\Models\CollectionRecord;
use App\Models\User;
use App\Support\CollectionBrowseIndex;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StoreCollectionRecordsAction
{
    public function __construct(protected StoreReleaseAction $storeRelease) {}

    /** @param array<int, array<string, mixed>> $records */
    public function execute(User $user, array $records): void
    {
        DB::transaction(function () use ($user, $records): void {
            $knownIds = $user->records()->whereIn('instance_id', array_column($records, 'instanceId'))->pluck('discogs_release_id')->all();
            $releases = [];
            foreach (collect($records)->unique('id') as $record) {
                $releases[$record['id']] = $this->storeRelease->execute($record['id'], $record, replace: in_array($record['id'], $knownIds, true))->recordData();
            }
            $now = now()->toDateTimeString();
            $rows = array_map(fn (array $record): array => [
                'instance_id' => $record['instanceId'],
                'user_id' => $user->id,
                'discogs_release_id' => $record['id'],
                'added_at' => CarbonImmutable::parse($record['addedAt'])->utc()->toDateTimeString(),
                ...CollectionBrowseIndex::attributes([...$record, ...$releases[$record['id']]], CarbonImmutable::parse($record['addedAt'])),
                'updated_at' => $now,
            ], $records);

            foreach (array_chunk($rows, 100) as $chunk) {
                CollectionRecord::query()->upsert(
                    $chunk,
                    ['user_id', 'instance_id'],
                    ['discogs_release_id', 'added_at', 'updated_at', 'browse_title', 'browse_original_year', 'browse_added_day', 'browse_search'],
                );
                CollectionBrowseIndex::sync($user->records()->whereIn('instance_id', array_column($chunk, 'instance_id'))
                    ->get(['id', 'user_id', 'discogs_release_id', 'instance_id', 'added_at']));
            }
        });
    }
}
