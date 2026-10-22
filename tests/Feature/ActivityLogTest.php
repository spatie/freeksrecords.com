<?php

use App\Models\Comment;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

it('logs social actions once without storing private content or invitation tokens', function () {
    Notification::fake();
    $owner = socialUser('owner', 'everyone');
    $viewer = socialUser('viewer');
    $record = socialRecord($owner);
    $this->actingAs($viewer);

    $this->post("/shelves/{$record->id}/appreciations")->assertRedirect();
    $this->post("/shelves/{$record->id}/appreciations")->assertRedirect();
    $this->delete("/shelves/{$record->id}/appreciations")->assertRedirect();
    $this->delete("/shelves/{$record->id}/appreciations")->assertRedirect();
    $this->post("/shelves/{$record->id}/comments", ['body' => 'Private comment body'])->assertRedirect();
    $comment = Comment::query()->sole();
    $this->delete("/shelves/{$record->id}/comments/{$comment->id}")->assertRedirect();
    $this->post('/friends/invite', ['email' => 'invited@example.test'])->assertRedirect();
    $this->post('/friends/invite', ['email' => 'invited@example.test'])->assertRedirect();

    $activities = Activity::query()->orderBy('id')->get();
    expect($activities->pluck('event')->all())->toBe([
        'record_appreciated', 'appreciation_removed', 'comment_added', 'comment_deleted', 'invitation_sent',
    ])->and($activities->pluck('causer_id')->unique()->all())->toBe([$viewer->id])
        ->and($activities->toJson())->not->toContain('Private comment body', 'token_hash');
});
