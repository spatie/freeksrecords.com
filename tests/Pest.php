<?php

use App\Models\CollectionRecord;
use App\Models\Friendship;
use App\Models\User;
use App\Support\RecordCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Storage::fake('public'))
    ->in('Feature');

function socialUser(string $handle, string $visibility = 'only_me'): User
{
    return User::query()->create(['name' => ucfirst($handle), 'handle' => $handle, 'email' => $handle.'@example.test', 'password' => 'a-long-demo-password', 'email_verified_at' => now(), 'visibility' => $visibility]);
}

function socialRecord(User $owner, string $title = 'Private Test Album', bool $activity = true, string $date = '2026-10-17 12:00:00', int $instance = 123): CollectionRecord
{
    $payload = app(RecordCollection::class)->snapshot()[0];
    $payload['displayTitle'] = $payload['title'] = $title;
    $payload['instanceId'] = $instance;

    return $owner->records()->create(['instance_id' => $instance, 'discogs_release_id' => $payload['id'], 'payload' => $payload, 'added_at' => $date, 'is_activity' => $activity]);
}

function socialFriends(User $a, User $b): Friendship
{
    return Friendship::query()->create(['user_low' => min($a->id, $b->id), 'user_high' => max($a->id, $b->id), 'requested_by' => $a->id, 'accepted_at' => now()]);
}

/** @return array<string, mixed> */
function discogsCollectionEntry(int $instanceId, int $releaseId): array
{
    return [
        'instance_id' => $instanceId,
        'date_added' => '2026-10-01T10:00:00-07:00',
        'basic_information' => [
            'id' => $releaseId,
            'master_id' => 999999991,
            'title' => 'A New Record',
            'year' => 2026,
            'artists' => [['name' => 'The Artist']],
            'labels' => [['name' => 'The Label', 'catno' => 'NEW-1']],
            'formats' => [['descriptions' => ['LP', 'Reissue']]],
            'genres' => ['Rock'],
            'styles' => [],
            'cover_image' => '',
        ],
    ];
}
