<?php

namespace App\Actions;

use App\Models\CollectionRecord;
use App\Models\Comment;
use App\Models\User;

class DeleteRecordCommentAction
{
    public function execute(User $user, CollectionRecord $record, Comment $comment): void
    {
        abort_unless($comment->collection_record_id === $record->id, 404);
        // Authors retain their deletion right even if they lose collection access.
        abort_if($user->id !== $comment->user_id && $user->id !== $record->user_id, 403);

        $comment->delete();
        activity('social')->causedBy($user)->performedOn($record)->withProperties(['comment_id' => $comment->id])
            ->event('comment_deleted')->log('Record comment deleted');
    }
}
