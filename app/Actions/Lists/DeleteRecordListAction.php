<?php

namespace App\Actions\Lists;

use App\Models\RecordList;
use App\Models\User;

class DeleteRecordListAction
{
    public function execute(User $user, RecordList $recordList): void
    {
        abort_unless($recordList->user_id === $user->id, 404);

        $recordList->delete();
    }
}
