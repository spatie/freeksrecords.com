<?php

namespace App\Actions;

use App\Models\CollectionRecord;
use App\Models\SocialNotification;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class AddRecordCommentAction
{
    public function execute(User $user, CollectionRecord $record, mixed $body): void
    {
        abort_unless($record->user->canViewCollection($user), 404);
        abort_unless($record->user->comments_enabled, 403);

        $data = Validator::make(['body' => $body], ['body' => ['required', 'string', 'max:2000']])->validate();
        $record->comments()->create(['user_id' => $user->id, 'body' => $data['body']]);
        SocialNotification::send($record->user, $user, 'comment', $record);
    }
}
