<?php

use App\Models\CollectionRecord;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequest;
use App\Support\RecordCollection;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('uses random version four UUIDs without changing numeric keys or relationships', function () {
    $owner = socialUser('opaque_owner', 'everyone');
    $friend = socialUser('opaque_friend');
    $record = socialRecord($owner);
    $friendship = socialFriends($owner, $friend);
    $comment = $record->comments()->create(['user_id' => $friend->id, 'body' => 'Great record']);
    $activity = activity()->causedBy($owner)->log('Public ID test');

    foreach ([$owner, $friend, $record, $friendship, $comment, $activity] as $model) {
        expect($model->id)->toBeInt()
            ->and(Str::isUuid($model->public_id, version: 4))->toBeTrue()
            ->and($model->getRouteKey())->toBe($model->public_id);
        $publicId = $model->public_id;
        $model->save();
        expect($model->fresh()->public_id)->toBe($publicId);
    }

    expect($record->user_id)->toBe($owner->id)
        ->and($comment->collection_record_id)->toBe($record->id);
    $this->get($record->url(false))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('recordOverlay.record.publicId', $record->public_id)
        ->where('recordOverlay.comments.data.0.publicId', $comment->public_id)
        ->where('person.publicId', $owner->public_id));
    $this->get('/collections?record='.$record->public_id)->assertOk();
    $this->get('/shelves/'.$record->public_id)->assertOk();
    $this->get('/'.$friend->handle.'/records/'.$record->public_id)->assertNotFound();
});

it('rejects every old numeric record URL without disclosing its replacement', function () {
    $owner = socialUser('opaque_owner', 'everyone');
    $record = socialRecord($owner);
    $comment = $record->comments()->create(['user_id' => $owner->id, 'body' => 'Comment']);
    $this->actingAs($owner);

    foreach ([
        '/shelves/'.$record->id,
        '/shelves/'.$record->id.'/cover',
        '/share/shelves/'.$record->id.'.jpg',
        '/'.$owner->handle.'/records/'.$record->id,
        '/collections?record='.$record->id,
        '/'.$owner->handle.'?record='.$record->id,
        '/record/'.$record->instance_id,
        '/record/'.$record->instance_id.'/old-slug',
        '/record/'.$record->instance_id.'/image.png',
        '/api/records/'.$record->instance_id,
    ] as $url) {
        $this->get($url)->assertNotFound()->assertHeaderMissing('Location');
    }
    $this->patch('/shelves/'.$record->id.'/notes')->assertNotFound();
    $this->post('/shelves/'.$record->id.'/appreciations')->assertNotFound();
    $this->delete('/shelves/'.$record->id.'/appreciations')->assertNotFound();
    $this->post('/shelves/'.$record->id.'/comments')->assertNotFound();
    $this->delete('/shelves/'.$record->public_id.'/comments/'.$comment->id)->assertNotFound();
    $this->delete('/shelves/'.$record->id.'/comments/'.$comment->public_id)->assertNotFound();
});

it('keeps opaque private records private and preserves comment and note authorization', function () {
    $owner = socialUser('opaque_owner', 'friends');
    $friend = socialUser('opaque_friend');
    $stranger = socialUser('opaque_stranger');
    $record = socialRecord($owner);
    socialFriends($owner, $friend);
    $base = '/shelves/'.$record->public_id;

    $this->get($base)->assertRedirect('/login');
    $this->get('/collections?record='.$record->public_id)->assertRedirect('/login');
    $this->actingAs($stranger)->get($base)->assertRedirect('/opaque_owner');
    $this->get('/collections?record='.$record->public_id)->assertRedirect('/opaque_owner');
    $this->post($base.'/comments', ['body' => 'Denied'])->assertNotFound();
    $this->flushSession();
    $this->actingAs($friend)->get($base)->assertOk();
    $this->patch($base.'/notes', ['shared_note' => '', 'private_note' => ''])->assertForbidden();
    $this->post($base.'/comments', ['body' => 'Allowed'])->assertRedirect();
    $comment = $record->comments()->firstOrFail();
    $this->flushSession();
    $this->actingAs($stranger)->delete($base.'/comments/'.$comment->public_id)->assertForbidden();
    $this->flushSession();
    $this->actingAs($owner)->delete($base.'/comments/'.$comment->public_id)->assertRedirect();
    expect($comment->fresh())->toBeNull();
});

it('supports opaque legacy URLs only for the immutable public collection', function () {
    $collection = app(RecordCollection::class);
    $payload = $collection->snapshot()[0];
    $collection->store($payload);
    $record = CollectionRecord::legacy()->firstOrFail();
    $this->getJson('/api/records/'.$record->public_id)->assertOk()->assertJsonPath('title', $payload['title']);
    $this->followingRedirects()->get('/record/'.$record->public_id)->assertOk();
    $this->get('/record/'.$record->public_id.'/image.png')->assertRedirect();
    $this->getJson('/api/records/'.$record->instance_id)->assertNotFound();
    $other = socialRecord(socialUser('other_owner', 'only_me'));
    $this->getJson('/api/records/'.$other->public_id)->assertNotFound();
    $this->get('/record/'.$other->public_id.'/image.png')->assertNotFound();
});

it('uses opaque friend actions and signed responses while invalidating numeric signed links', function () {
    Notification::fake();
    $sender = socialUser('opaque_sender');
    $recipient = socialUser('opaque_recipient');
    $this->actingAs($sender)->post('/friends/'.$recipient->id, ['action' => 'request'])->assertNotFound();
    $this->post('/friends/'.$recipient->public_id, ['action' => 'request'])->assertRedirect();
    $friendship = Friendship::query()->firstOrFail();
    $url = (new FriendRequest($friendship->id))->toMail($recipient)->viewData['links']['accept'];
    expect($url)->toContain('/'.$friendship->public_id.'/'.$recipient->public_id.'/accept?');
    $numeric = URL::temporarySignedRoute('friendRequest.respond', now()->addHour(), [
        'friendship' => $friendship->id, 'recipient' => $recipient->id, 'action' => 'accept',
    ]);
    $this->post('/logout');
    $this->get($numeric)->assertNotFound();
    $this->post($numeric)->assertNotFound();
    $this->get($url)->assertOk();
    expect($friendship->fresh()->accepted_at)->toBeNull();
    $this->post($url)->assertRedirect($url);
    expect($friendship->fresh()->accepted_at)->not->toBeNull();
});

it('uses opaque verification and admin URLs without accepting numeric replacements', function () {
    $user = socialUser('opaque_unverified');
    $user->update(['email_verified_at' => null]);
    $url = (new VerifyEmail)->toMail($user)->viewData['url'];
    expect($url)->toContain('/verify-email/'.$user->public_id.'/');
    $this->actingAs($user);
    $numeric = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($numeric)->assertNotFound();
    $this->get($url)->assertRedirect('/feed');
    $this->flushSession();
    $freek = User::freek();
    $freek->update(['email_verified_at' => now()]);
    $activity = activity()->causedBy($user)->log('Opaque admin activity');
    $this->actingAs($freek)->get('/admin/users/'.$user->public_id)->assertOk();
    $this->get('/admin/activities/'.$activity->public_id)->assertOk();
    $this->get('/admin/users/'.$user->id)->assertNotFound();
    $this->get('/admin/activities/'.$activity->id)->assertNotFound();
    $this->actingAs($user)->get('/admin/users/'.$freek->public_id)->assertForbidden();
});

it('assigns stable public IDs to bulk imports without replacing existing IDs', function () {
    $collection = app(RecordCollection::class);
    $records = array_slice($collection->snapshot(), 0, 2);
    $collection->storeMany($records);
    $ids = CollectionRecord::legacy()->pluck('public_id', 'id')->all();
    $collection->storeMany($records);
    expect(CollectionRecord::legacy()->pluck('public_id', 'id')->all())->toBe($ids);
    foreach ($ids as $publicId) {
        expect(Str::isUuid($publicId, version: 4))->toBeTrue();
    }
});

it('backfills existing IDs in batches without modifying data or timestamps', function () {
    $connection = DB::getDefaultConnection();
    config(['database.connections.public_ids_migration' => [...config('database.connections.sqlite'), 'database' => ':memory:']]);
    DB::setDefaultConnection('public_ids_migration');

    try {
        $tables = ['collection_records', 'users', 'friendships', 'comments', 'activity_log'];
        $before = [];
        foreach ($tables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('body');
                $table->timestamp('updated_at')->nullable();
            });
            foreach (array_chunk(range(1, $tableName === 'collection_records' ? 502 : 2), 100) as $ids) {
                DB::table($tableName)->insert(array_map(fn (int $id): array => [
                    'id' => $id, 'body' => "Keep row {$id}", 'updated_at' => '2026-10-01 12:00:00',
                ], $ids));
            }
            $before[$tableName] = DB::table($tableName)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        }
        $updates = 0;
        DB::listen(function (QueryExecuted $query) use (&$updates): void {
            if (str_starts_with($query->sql, 'UPDATE ') && str_contains($query->sql, 'SET public_id = CASE id')) {
                $updates++;
            }
        });
        $migration = require database_path('migrations/2026_10_23_140000_add_public_ids.php');
        $migration->up();

        expect($updates)->toBe(6);
        foreach ($tables as $tableName) {
            $rows = DB::table($tableName)->orderBy('id')->get();
            expect($rows->map(fn ($row): array => Arr::except((array) $row, ['public_id']))->all())->toBe($before[$tableName])
                ->and($rows->pluck('public_id')->unique())->toHaveCount($rows->count());
            foreach ($rows as $row) {
                expect(Str::isUuid($row->public_id, version: 4))->toBeTrue();
            }
        }
    } finally {
        DB::purge('public_ids_migration');
        DB::setDefaultConnection($connection);
    }
});
