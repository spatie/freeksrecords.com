<?php

namespace App\Actions;

use App\Models\CollectionRecord;
use App\Models\SocialNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateRecordAppreciationAction
{
    public function execute(User $user, CollectionRecord $record, bool $appreciated): void
    {
        abort_unless($record->user->canViewCollection($user), 404);

        if (! $appreciated) {
            DB::table('appreciations')->where('user_id', $user->id)->where('collection_record_id', $record->id)->delete();

            return;
        }

        $created = DB::table('appreciations')->insertOrIgnore(['user_id' => $user->id, 'collection_record_id' => $record->id]);
        if ($created) {
            SocialNotification::send($record->user, $user, 'appreciation', $record);
        }
    }
}
