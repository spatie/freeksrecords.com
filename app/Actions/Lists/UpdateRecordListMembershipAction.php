<?php

namespace App\Actions\Lists;

use App\Models\CollectionRecord;
use App\Models\RecordList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRecordListMembershipAction
{
    public function execute(User $user, RecordList $recordList, CollectionRecord $copy, bool $containsRecord): void
    {
        abort_unless($recordList->user_id === $user->id, 404);
        abort_unless($copy->user_id === $user->id, 404);

        DB::transaction(function () use ($recordList, $copy, $containsRecord): void {
            $list = RecordList::query()->lockForUpdate()->findOrFail($recordList->id);

            if (! $containsRecord) {
                if ($list->records()->detach($copy->id)) {
                    $list->touch();
                }

                return;
            }

            if ($list->records()->whereKey($copy->id)->exists()) {
                return;
            }

            if ($list->records()->count() >= 1000) {
                throw ValidationException::withMessages(['record' => 'Lists can contain up to 1,000 records.']);
            }

            $position = DB::table('record_list_entries')->where('record_list_id', $list->id)->max('position');
            $list->records()->attach($copy, ['position' => $position === null ? 0 : $position + 1]);
            $list->touch();
        });
    }
}
