<?php

namespace App\Actions\Social;

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
            $removed = DB::table('appreciations')->where('user_id', $user->id)->where('collection_record_id', $record->id)->delete();
            if ($removed) {
                activity('social')->causedBy($user)->performedOn($record)
                    ->event('appreciation_removed')->log('Record appreciation removed');
            }

            return;
        }

        $created = DB::table('appreciations')->insertOrIgnore(['user_id' => $user->id, 'collection_record_id' => $record->id]);
        if ($created) {
            activity('social')->causedBy($user)->performedOn($record)
                ->event('record_appreciated')->log('Record appreciated');
            SocialNotification::send($record->user, $user, 'appreciation', $record);
        }
    }
}
