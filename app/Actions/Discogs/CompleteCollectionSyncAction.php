<?php

namespace App\Actions\Discogs;

use App\Models\User;
use App\Notifications\CollectionImported;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompleteCollectionSyncAction
{
    /** @param list<int> $seen */
    public function execute(User $user, array $seen, bool $full, CarbonImmutable $startedAt): bool
    {
        return DB::transaction(function () use ($user, $seen, $full, $startedAt): bool {
            $initial = $user->collection_imported_at === null;
            $removed = 0;
            if ($full) {
                $removed = $user->records()->whereNotIn('instance_id', $seen)->delete();
                $user->last_synced_at = now();
            }
            $user->update([
                'collection_imported_at' => $user->collection_imported_at ?? now(),
                'discogs_additions_checked_at' => $startedAt,
                'sync_status' => $full ? 'Up to date' : 'Additions checked', 'discogs_sync_state' => null,
            ]);
            if ($initial) {
                activity('discogs')->causedBy($user)->performedOn($user)->withProperties(['records_count' => count($seen)])
                    ->event('initial_import_completed')->log('Initial collection import completed');

                $recordCount = $user->records()->count();

                if ($recordCount > 50) {
                    $user->notify(new CollectionImported($recordCount));
                }
            }

            return $removed > 0;
        });
    }
}
