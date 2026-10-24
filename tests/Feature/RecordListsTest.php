<?php

use App\Actions\Collections\StoreCollectionRecordAction;
use App\Models\CollectionRecord;
use App\Models\RecordList;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('backfills stable unique list public ids without changing existing lists or their entries', function () {
    $connection = DB::getDefaultConnection();
    config(['database.connections.list_migration' => [...config('database.connections.sqlite'), 'database' => ':memory:']]);
    DB::setDefaultConnection('list_migration');

    try {
        $this->artisan('migrate', ['--database' => 'list_migration'])->assertSuccessful();
        $owner = socialUser('list_owner');
        $copy = socialRecord($owner);
        Schema::table('record_lists', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
        DB::table('record_lists')->insert([
            ['id' => 3, 'user_id' => $owner->id, 'name' => 'Test', 'description' => 'Keep this.', 'visibility' => 'friends', 'created_at' => '2026-10-22 12:00:00', 'updated_at' => '2026-10-22 13:00:00'],
            ['id' => 4, 'user_id' => $owner->id, 'name' => 'Another list', 'description' => null, 'visibility' => 'only_me', 'created_at' => '2026-10-22 12:00:00', 'updated_at' => '2026-10-22 13:00:00'],
        ]);
        DB::table('record_list_entries')->insert([
            ['record_list_id' => 3, 'collection_record_id' => $copy->id, 'position' => 7],
            ['record_list_id' => 3, 'collection_record_id' => null, 'position' => 8],
        ]);
        $lists = DB::table('record_lists')->orderBy('id')->get()->map(fn ($list) => (array) $list)->all();
        $entries = DB::table('record_list_entries')->orderBy('id')->get()->all();
        $migration = require database_path('migrations/2026_10_23_190000_add_public_ids_to_record_lists.php');

        $migration->up();

        $publicIds = RecordList::query()->orderBy('id')->pluck('public_id')->all();
        expect($publicIds)->toHaveCount(2)->and(array_unique($publicIds))->toHaveCount(2);
        foreach ($publicIds as $publicId) {
            expect(Str::isUuid($publicId))->toBeTrue();
        }
        expect(DB::table('record_lists')->orderBy('id')->get()->map(fn ($list) => collect((array) $list)->except('public_id')->all())->all())->toBe($lists)
            ->and(DB::table('record_list_entries')->orderBy('id')->get()->all())->toEqual($entries);

        $migration->up();

        expect(RecordList::query()->orderBy('id')->pluck('public_id')->all())->toBe($publicIds)
            ->and(DB::table('record_list_entries')->orderBy('id')->get()->all())->toEqual($entries);
    } finally {
        DB::purge('list_migration');
        DB::setDefaultConnection($connection);
    }
});

it('uses UUID list and copy routes while retaining numeric selection ids', function () {
    $owner = socialUser('list_owner');
    $copy = socialRecord($owner);
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'UUID list']);
    $list->records()->attach($copy, ['position' => 0]);

    $this->actingAs($owner)->get('/lists')->assertInertia(fn (Assert $page) => $page
        ->where('lists.0.id', $list->id)->where('lists.0.publicId', $list->public_id));
    $this->get("/lists/{$list->id}")->assertNotFound();
    $this->get("/lists/{$list->id}/edit")->assertNotFound();
    $this->patch("/lists/{$list->id}", ['name' => 'Wrong route'])->assertNotFound();
    $this->delete("/lists/{$list->id}")->assertNotFound();
    foreach (["/lists/{$list->id}/records/{$copy->public_id}", "/lists/{$list->public_id}/records/{$copy->id}"] as $url) {
        $this->get($url)->assertNotFound();
        $this->get($url.'/cover')->assertNotFound();
        $this->post($url)->assertNotFound();
        $this->delete($url)->assertNotFound();
    }
    expect($list->fresh()->name)->toBe('UUID list')->and($list->records()->count())->toBe(1);
});

it('previews the first four ordered covers of each owned list while counting all records', function () {
    $owner = socialUser('list_owner');
    $copies = collect(range(1, 6))->map(fn (int $index) => socialRecord($owner, "Record {$index}", instance: 100 + $index));
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Six records']);
    foreach ($copies->reverse()->values() as $position => $copy) {
        $list->records()->attach($copy, ['position' => $position]);
    }
    $small = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'One record', 'updated_at' => now()->subMinute()]);
    $small->records()->attach($copies->first(), ['position' => 0]);
    $empty = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Empty', 'updated_at' => now()->subMinutes(2)]);
    RecordList::query()->create(['user_id' => socialUser('other_owner')->id, 'name' => 'Not mine', 'visibility' => 'everyone']);

    $this->actingAs($owner)->get('/lists')->assertInertia(fn (Assert $page) => $page
        ->has('lists', 3)
        ->where('lists.0.publicId', $list->public_id)->where('lists.0.count', 6)
        ->where('lists.0.covers', $copies->reverse()->take(4)->map(fn (CollectionRecord $copy) => route('lists.cover', [$list, $copy], false))->values()->all())
        ->where('lists.1.publicId', $small->public_id)->where('lists.1.count', 1)
        ->where('lists.1.covers', [route('lists.cover', [$small, $copies->first()], false)])
        ->where('lists.2.publicId', $empty->public_id)->where('lists.2.count', 0)->has('lists.2.covers', 0));
});

it('preserves the audience when replacing inherited list visibility', function () {
    foreach (['only_me', 'friends', 'everyone'] as $visibility) {
        $owner = socialUser("owner_{$visibility}", $visibility);
        $inherited = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Inherited', 'visibility' => 'collection']);
        $private = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Private']);

        (require database_path('migrations/2026_10_23_100000_make_list_visibility_independent.php'))->up();

        expect($inherited->fresh()->visibility)->toBe($visibility)
            ->and($private->fresh()->visibility)->toBe('only_me');
    }
});

it('shows a placeholder for listed records whose artwork is not available yet', function () {
    $owner = socialUser('list_owner');
    $copy = socialRecord($owner, 'Awaiting artwork');
    app(StoreCollectionRecordAction::class)->execute($copy, ['payload' => [...$copy->payload, 'cover' => '/images/record-placeholder.svg']]);
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'New imports', 'visibility' => 'everyone']);
    $list->records()->attach($copy, ['position' => 0]);

    $this->get('/collections?tab=lists')->assertInertia(fn (Assert $page) => $page
        ->where('lists.data.0.covers.0', route('lists.cover', [$list, $copy], false)));
    $this->get(route('lists.cover', [$list, $copy], false))->assertRedirect('/images/record-placeholder.svg');
    $details = $this->getJson(route('lists.record', [$list, $copy], false))->assertOk();
    $this->get($details->json('cover'))->assertRedirect('/images/record-placeholder.svg');
});

it('creates private lists and saves only owned copies in the chosen order', function () {
    $owner = socialUser('list_owner');
    $other = socialUser('list_other');
    $first = socialRecord($owner, 'First pick', instance: 101);
    $second = socialRecord($owner, 'Second pick', instance: 102);
    $foreign = socialRecord($other, 'Not mine', instance: 101);

    $this->actingAs($owner)->post('/lists', ['name' => 'Sunday records', 'description' => 'Slow mornings.', 'user_id' => $other->id])->assertRedirect();
    $list = RecordList::query()->sole();
    expect($list->visibility)->toBe('only_me')->and($list->user_id)->toBe($owner->id);

    $this->patch("/lists/{$list->public_id}", ['recordIds' => [$second->id, $first->id]])
        ->assertRedirect('/lists')->assertSessionHas('status', 'List saved.');
    $this->get("/lists/{$list->public_id}")->assertInertia(fn (Assert $page) => $page
        ->component('social/ListPage')->where('list.id', $list->id)->where('list.publicId', $list->public_id)
        ->where('records.0.copyId', $second->id)->where('records.0.copyPublicId', $second->public_id)->where('records.1.copyId', $first->id));
    $this->patch("/lists/{$list->public_id}", ['name' => 'Do not save', 'visibility' => 'everyone', 'recordIds' => [$foreign->id]])->assertSessionHasErrors('recordIds.0');
    $this->patch("/lists/{$list->public_id}", ['recordIds' => [$first->id, $first->id]])->assertSessionHasErrors('recordIds.0');
    $this->patch("/lists/{$list->public_id}", ['recordIds' => ['wrongPosition' => $first->id]])->assertSessionHasErrors('recordIds');
    expect($list->fresh()->name)->toBe('Sunday records')->and($list->fresh()->visibility)->toBe('only_me');

    $this->patch("/lists/{$list->public_id}", ['recordIds' => [$first->id]])->assertSessionHasNoErrors();
    $this->get("/lists/{$list->public_id}")->assertInertia(fn (Assert $page) => $page->has('records', 1)->where('records.0.copyId', $first->id));
    $this->patch("/lists/{$list->public_id}", ['recordIds' => []])->assertSessionHasNoErrors();
    $this->get("/lists/{$list->public_id}")->assertInertia(fn (Assert $page) => $page->has('records', 0));
});

it('creates a list with the chosen visibility without changing collection privacy', function (string $visibility) {
    $owner = socialUser('list_owner');
    $other = socialUser('list_other');

    $this->actingAs($owner)->post('/lists', ['name' => 'Chosen audience', 'visibility' => $visibility, 'user_id' => $other->id])->assertSessionHasNoErrors();
    $list = RecordList::query()->sole();
    expect($list->visibility)->toBe($visibility)->and($list->user_id)->toBe($owner->id)
        ->and($owner->fresh()->visibility)->toBe('only_me');
    $this->patch("/lists/{$list->public_id}", ['visibility' => $visibility])
        ->assertSessionHasNoErrors()->assertRedirect("/lists/{$list->public_id}");

    $this->app['auth']->forgetGuards();
    $this->flushSession();
    if ($visibility === 'friends') {
        $this->get("/lists/{$list->public_id}")->assertRedirect('/login');

        return;
    }
    $visibility === 'everyone'
        ? $this->get("/lists/{$list->public_id}")->assertOk()
        : $this->get("/lists/{$list->public_id}")->assertNotFound();
})->with(['only_me', 'friends', 'everyone']);

it('rejects unsupported visibility when creating a list', function () {
    $this->actingAs(socialUser('list_owner'))->post('/lists', ['name' => 'Invalid audience', 'visibility' => 'collection'])->assertSessionHasErrors('visibility');
    $this->assertDatabaseCount('record_lists', 0);
});

it('creates a list from an owned release and returns to that release with the record selected', function () {
    $owner = socialUser('list_owner');
    $copy = socialRecord($owner, 'Pick from a release');
    $foreign = socialRecord(socialUser('list_other'), 'Not owned');

    $this->actingAs($owner)->from(route('profile', $owner->handle))->post('/lists', ['name' => 'New from release', 'visibility' => 'everyone', 'copyId' => $copy->id])->assertRedirect($copy->url(false));
    $list = RecordList::query()->sole();
    expect($list->records()->pluck('collection_records.id')->all())->toBe([$copy->id]);
    $this->get($copy->url(false))->assertInertia(fn (Assert $page) => $page
        ->where('recordOverlay.record.lists.0.id', $list->id)->where('recordOverlay.record.lists.0.publicId', $list->public_id)
        ->where('recordOverlay.record.lists.0.name', 'New from release')->where('recordOverlay.record.lists.0.containsRecord', true));
    $this->post('/lists', ['name' => 'Not allowed', 'copyId' => $foreign->id])->assertSessionHasErrors('copyId');
    $this->assertDatabaseCount('record_lists', 1);
});

it('appends a release to an owned list once without changing its existing order', function () {
    $owner = socialUser('list_owner');
    $first = socialRecord($owner, 'First');
    $copy = socialRecord($owner, 'Append me', instance: 100);
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Existing list']);
    $list->records()->attach($first, ['position' => 7]);
    $other = socialUser('list_other');
    $foreignList = RecordList::query()->create(['user_id' => $other->id, 'name' => 'Foreign list']);
    $foreign = socialRecord($other, 'Not owned');

    $this->actingAs($owner)->from(route('profile', $owner->handle))->post("/lists/{$list->public_id}/records/{$copy->public_id}")->assertRedirect($copy->url(false));
    $this->post("/lists/{$list->public_id}/records/{$copy->public_id}")->assertSessionHasNoErrors();
    $this->post("/lists/{$list->public_id}/records/{$first->public_id}")->assertSessionHasNoErrors();
    expect($list->records()->pluck('collection_records.id')->all())->toBe([$first->id, $copy->id]);
    $this->delete("/lists/{$list->public_id}/records/{$first->public_id}")->assertRedirect($first->url(false));
    $this->delete("/lists/{$list->public_id}/records/{$first->public_id}")->assertSessionHasNoErrors();
    $this->get("/lists/{$list->public_id}")->assertInertia(fn (Assert $page) => $page
        ->has('records', 1)->where('records.0.copyId', $copy->id));
    $this->post("/lists/{$list->public_id}/records/{$first->public_id}")->assertSessionHasNoErrors();
    expect($list->records()->pluck('collection_records.id')->all())->toBe([$copy->id, $first->id]);
    $this->post("/lists/{$foreignList->public_id}/records/{$copy->public_id}")->assertNotFound();
    $this->post("/lists/{$list->public_id}/records/{$foreign->public_id}")->assertNotFound();
    $this->delete("/lists/{$foreignList->public_id}/records/{$copy->public_id}")->assertNotFound();
    $this->delete("/lists/{$list->public_id}/records/{$foreign->public_id}")->assertNotFound();
    $this->actingAs($other)->get($first->url(false))->assertRedirect(route('profile', $owner->handle));
    $owner->update(['visibility' => 'everyone']);
    $this->get($first->url(false))->assertInertia(fn (Assert $page) => $page->has('recordOverlay.record.lists', 0));
    $owner->update(['email_verified_at' => null]);
    $this->actingAs($owner)->post("/lists/{$list->public_id}/records/{$copy->public_id}")->assertRedirect('/verify-email');
    $this->delete("/lists/{$list->public_id}/records/{$copy->public_id}")->assertRedirect('/verify-email');
});

it('keeps capacity errors on the release without changing a full list', function () {
    $owner = socialUser('list_owner');
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Nearly full']);
    $last = socialRecord($owner, instance: 1000);
    $extra = socialRecord($owner, instance: 1001);
    $attributes = $last->getAttributes();
    unset($attributes['id']);
    $copies = [];
    foreach (range(1, 999) as $instance) {
        $copies[] = [...$attributes, 'public_id' => (string) Str::uuid(), 'instance_id' => $instance];
    }
    CollectionRecord::query()->insert($copies);
    $list->records()->attach($owner->records()->where('instance_id', '<', 1000)->pluck('id')
        ->mapWithKeys(fn (int $id, int $position) => [$id => ['position' => $position]])->all());

    $this->actingAs($owner)->from(route('profile', $owner->handle))
        ->post("/lists/{$list->public_id}/records/{$last->public_id}")->assertSessionHasNoErrors();
    $this->post("/lists/{$list->public_id}/records/{$extra->public_id}")
        ->assertRedirect($extra->url(false))->assertSessionHasErrors('record');
    expect($list->records()->count())->toBe(1000)
        ->and($list->records()->whereKey($extra->id)->exists())->toBeFalse();
    $this->post("/lists/{$list->public_id}/records/{$last->public_id}")->assertSessionHasNoErrors();
});

it('keeps standalone release pages open when adding to or creating a list', function () {
    $owner = socialUser('list_owner');
    $copy = socialRecord($owner, 'Standalone release');
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Existing']);

    $this->actingAs($owner)->from(route('profile', $owner->handle))
        ->withHeader('X-Inertia-Partial-Component', 'social/RecordPage')
        ->post("/lists/{$list->public_id}/records/{$copy->public_id}")->assertRedirect(route('shelf.record', $copy));
    $this->post('/lists', ['name' => 'New', 'visibility' => 'everyone', 'copyId' => $copy->id])->assertRedirect(route('shelf.record', $copy));
    $this->get(route('shelf.record', $copy))->assertInertia(fn (Assert $page) => $page
        ->component('social/RecordPage')->has('record.lists', 2)
        ->where('record.lists.0.visibility', 'only_me')->where('record.lists.1.visibility', 'everyone')
        ->where('record.lists.0.containsRecord', true)->where('record.lists.1.containsRecord', true));
    $this->delete("/lists/{$list->public_id}/records/{$copy->public_id}")->assertRedirect(route('shelf.record', $copy));
    $this->get(route('shelf.record', $copy))->assertInertia(fn (Assert $page) => $page
        ->component('social/RecordPage')->where('record.lists.0.containsRecord', false)
        ->where('record.lists.1.containsRecord', true));
});

it('requires verification and never allows another collector to manage lists', function () {
    $owner = socialUser('list_owner');
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Hidden list']);
    $other = socialUser('list_other');

    $this->get('/lists')->assertRedirect('/login');
    $this->post('/lists', ['name' => 'Nope'])->assertRedirect('/login');
    $this->actingAs($other)->get('/lists')->assertInertia(fn (Assert $page) => $page->has('lists', 0));
    $this->get("/lists/{$list->public_id}/edit")->assertNotFound();
    $this->patch("/lists/{$list->public_id}", ['name' => 'Hijacked'])->assertNotFound();
    $this->delete("/lists/{$list->public_id}")->assertNotFound();
    $other->update(['email_verified_at' => null]);
    $this->post('/lists', ['name' => 'Nope'])->assertRedirect('/verify-email');
    expect($list->fresh()->name)->toBe('Hidden list');
});

it('enforces list access separately from collection access for pages details and covers', function (string $listVisibility, string $collectionVisibility, string $audience, bool $allowed) {
    $owner = socialUser('list_owner', $collectionVisibility);
    $copy = socialRecord($owner, 'Listed record');
    app(StoreCollectionRecordAction::class)->execute($copy, ['payload' => [...$copy->payload, 'cover' => '/images/demo-sleeve-1.jpg']]);
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Selected records', 'visibility' => $listVisibility]);
    $list->records()->attach($copy, ['position' => 0]);
    if ($audience !== 'guest') {
        $viewer = $audience === 'owner' ? $owner : socialUser('list_viewer');
        if (in_array($audience, ['friend', 'pending'])) {
            $friendship = socialFriends($owner, $viewer);
            if ($audience === 'pending') {
                $friendship->update(['accepted_at' => null]);
            }
        }
        $this->actingAs($viewer);
    }

    foreach (["/lists/{$list->public_id}", "/lists/{$list->public_id}/records/{$copy->public_id}", "/lists/{$list->public_id}/records/{$copy->public_id}/cover"] as $url) {
        $response = $this->get($url);
        if ($url === "/lists/{$list->public_id}" && $listVisibility === 'friends' && $audience === 'guest') {
            $response->assertRedirect('/login');
        } else {
            $allowed ? $response->assertOk() : $response->assertNotFound();
        }
        expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue()
            ->and($response->headers->hasCacheControlDirective('private'))->toBeTrue();
    }
})->with([
    ['only_me', 'everyone', 'guest', false], ['only_me', 'everyone', 'friend', false], ['only_me', 'only_me', 'owner', true],
    ['friends', 'only_me', 'friend', true], ['friends', 'friends', 'friend', true], ['friends', 'friends', 'pending', false],
    ['friends', 'friends', 'guest', false], ['friends', 'everyone', 'guest', false], ['friends', 'friends', 'stranger', false],
    ['friends', 'everyone', 'stranger', false], ['friends', 'only_me', 'owner', true],
    ['everyone', 'only_me', 'guest', true], ['everyone', 'friends', 'stranger', true],
]);

it('publishes only selected record metadata without opening the private collection or its social data', function () {
    $owner = socialUser('list_owner');
    $copy = socialRecord($owner, 'Public selection');
    app(StoreCollectionRecordAction::class)->execute($copy, ['shared_note' => 'A shared secret', 'private_note' => 'A private secret', 'payload' => [...$copy->payload, 'cover' => '/images/demo-sleeve-1.jpg', 'secretPayload' => 'Never serialize me']]);
    $hidden = socialRecord($owner, 'Still private', instance: 456);
    $copy->comments()->create(['user_id' => $owner->id, 'body' => 'A private conversation']);
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Just these']);
    $list->records()->attach($copy, ['position' => 0]);

    $this->actingAs($owner)->patch("/lists/{$list->public_id}", ['visibility' => 'everyone'])->assertSessionHasNoErrors();
    expect($owner->fresh()->visibility)->toBe('only_me');
    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->get("/lists/{$list->public_id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('records', 1)->where('records.0.copyId', $copy->id)->where('list.canEdit', false)
        ->where('socialPreview.title', 'Just these · List_owner'))
        ->assertDontSee(['A shared secret', 'A private secret', 'Still private', 'A private conversation', 'Never serialize me']);
    $details = $this->getJson("/lists/{$list->public_id}/records/{$copy->public_id}")->assertOk()->assertJsonPath('displayTitle', 'Public selection')
        ->assertJsonMissingPath('shared_note')->assertJsonMissingPath('private_note')->assertJsonMissingPath('secretPayload')->assertJsonMissingPath('user');
    expect($details->json('tracks.0.title'))->toBe($copy->payload['tracks'][0]['title']);
    $this->get("/lists/{$list->public_id}/records/{$hidden->public_id}")->assertNotFound();
    $this->get("/lists/{$list->public_id}/records/{$hidden->public_id}/cover")->assertNotFound();
    $this->get($hidden->url(false))->assertRedirect('/login');
    $this->get("/shelves/{$copy->public_id}/cover")->assertNotFound();

    $this->get("/lists/{$list->public_id}/records/{$copy->public_id}/cover")->assertOk();
    $this->actingAs($owner)->patch("/lists/{$list->public_id}", ['visibility' => 'only_me'])->assertSessionHasNoErrors();
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->get("/lists/{$list->public_id}")->assertNotFound();
    $this->get("/lists/{$list->public_id}/records/{$copy->public_id}")->assertNotFound();
    $this->get("/lists/{$list->public_id}/records/{$copy->public_id}/cover")->assertNotFound();
});

it('finds owned records across picker pages and searches without returning other collections', function () {
    $owner = socialUser('list_owner');
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Picking']);
    foreach (range(1, 38) as $number) {
        socialRecord($owner, "Album {$number}", instance: $number);
    }
    $target = socialRecord($owner, 'Café selection', instance: 100);
    socialRecord(socialUser('list_other'), 'Café selection', instance: 100);
    $list->records()->attach($target, ['position' => 0]);
    $this->actingAs($owner)->get("/lists/{$list->public_id}/edit")->assertInertia(fn (Assert $page) => $page
        ->component('social/ListEditPage')->has('results.data', 36)->has('records', 1)->where('records.0.copyId', $target->id));
    $this->get("/lists/{$list->public_id}/edit?recordsPage=2")->assertInertia(fn (Assert $page) => $page
        ->has('results.data', 3)->has('records', 1)->where('records.0.copyId', $target->id));
    $this->get("/lists/{$list->public_id}/edit?q=cafe%20selection")->assertInertia(fn (Assert $page) => $page
        ->has('results.data', 1)->where('results.data.0.copyId', $target->id)->where('results.data.0.copyPublicId', $target->public_id));
    $this->get("/lists/{$list->public_id}/edit?q=nonexistent")->assertInertia(fn (Assert $page) => $page->has('results.data', 0)->has('records', 1));
});

it('removes deleted copies from lists and informs only the owner until they save again', function () {
    $owner = socialUser('list_owner');
    $removed = socialRecord($owner, 'Removed from Discogs');
    $kept = socialRecord($owner, 'Still owned', instance: 456);
    $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => 'My selection', 'visibility' => 'everyone']);
    $list->records()->attach([$removed->id => ['position' => 0], $kept->id => ['position' => 1]]);
    $owner->records()->whereKey($removed->id)->delete();

    $this->get("/lists/{$list->public_id}")->assertInertia(fn (Assert $page) => $page->has('records', 1)->where('list.removedCount', 0));
    $this->actingAs($owner)->get("/lists/{$list->public_id}")->assertInertia(fn (Assert $page) => $page
        ->has('records', 1)->where('records.0.copyId', $kept->id)->where('list.removedCount', 1));
    $this->patch("/lists/{$list->public_id}", ['recordIds' => [$kept->id]])->assertSessionHasNoErrors();
    $this->get("/lists/{$list->public_id}")->assertInertia(fn (Assert $page) => $page->where('list.removedCount', 0));
    $this->delete("/lists/{$list->public_id}")->assertRedirect('/lists');
    expect($kept->fresh())->not->toBeNull();
    $this->assertDatabaseCount('record_list_entries', 0);
});

it('discovers only accessible lists on a private collectors profile', function (string $audience, int $count) {
    $owner = socialUser('list_owner');
    $copy = socialRecord($owner, 'Never reveal the private shelf');
    foreach (['everyone', 'friends', 'only_me'] as $visibility) {
        $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => $visibility, 'visibility' => $visibility]);
        $list->records()->attach($copy, ['position' => 0]);
    }
    RecordList::query()->create(['user_id' => socialUser('other_owner')->id, 'name' => 'Not this collector', 'visibility' => 'everyone']);
    if ($audience !== 'guest') {
        $viewer = $audience === 'owner' ? $owner : socialUser('list_viewer');
        if (in_array($audience, ['friend', 'pending'])) {
            $friendship = socialFriends($owner, $viewer);
            if ($audience === 'pending') {
                $friendship->update(['accepted_at' => null]);
            }
        }
        $this->actingAs($viewer);
    }

    $this->get('/list_owner/lists')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('social/ProfileListsPage')->where('person.handle', 'list_owner')
        ->where('listCount', $count)->has('lists.data', $count)->missing('records')
        ->where('lists.data.0.visibility', $audience === 'owner' ? 'only_me' : ($audience === 'friend' ? 'friends' : 'everyone')));
    $profile = $this->get('/list_owner')->assertOk();
    $profile->assertInertia(fn (Assert $page) => $page->where('listCount', $count));
    if ($audience !== 'owner') {
        $profile->assertInertia(fn (Assert $page) => $page->where('visible', false)->where('records', null));
        foreach ([$copy->url(false), "/list_owner?record={$copy->public_id}"] as $url) {
            $this->get($url)->assertRedirect($audience === 'guest' ? '/login' : '/list_owner');
        }
        $this->get("/list_owner/lists?record={$copy->public_id}")->assertNotFound();
    }
})->with([['guest', 1], ['stranger', 1], ['pending', 1], ['friend', 2], ['owner', 3]]);

it('paginates public lists independently of collection visibility and removes hidden lists immediately', function () {
    $owner = socialUser('private_collector');
    $viewer = socialUser('list_viewer');
    socialFriends($owner, $viewer);
    $copy = socialRecord($owner);
    foreach (range(1, 13) as $index) {
        $list = RecordList::query()->create(['user_id' => $owner->id, 'name' => "Selection {$index}", 'visibility' => 'everyone']);
        $list->records()->attach($copy, ['position' => 0]);
    }
    foreach (['friends', 'only_me'] as $visibility) {
        RecordList::query()->create(['user_id' => $owner->id, 'name' => "Hidden {$visibility}", 'visibility' => $visibility]);
    }

    $this->get('/private_collector/lists')->assertInertia(fn (Assert $page) => $page
        ->where('listCount', 13)->has('lists.data', 12)->where('lists.next_page_url', url('/private_collector/lists?page=2')));
    $this->get('/private_collector/lists?page=2')->assertInertia(fn (Assert $page) => $page
        ->where('listCount', 13)->has('lists.data', 1)->where('lists.data.0.name', 'Selection 1'));
    $this->actingAs($viewer)->get('/collections?tab=lists')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tab', 'lists')->has('lists.data', 12)->missing('collectors')
        ->where('lists.data.0.name', 'Selection 13')->where('lists.data.0.owner.handle', 'private_collector')
        ->where('lists.data.0.count', 1)->has('lists.data.0.covers', 1)
        ->where('lists.next_page_url', url('/collections?tab=lists&page=2')));
    $this->get('/collections?tab=lists&page=2')->assertInertia(fn (Assert $page) => $page
        ->has('lists.data', 1)->where('lists.data.0.name', 'Selection 1')->where('lists.next_page_url', null));
    $list->update(['visibility' => 'only_me']);
    $this->get('/collections?tab=lists')->assertInertia(fn (Assert $page) => $page
        ->has('lists.data', 12)->where('lists.data.0.name', 'Selection 12')->where('lists.next_page_url', null));
    $this->get('/collections')->assertInertia(fn (Assert $page) => $page->has('collectors.data', 0)->missing('lists'));
    $this->get('/list_owner_missing/lists')->assertNotFound();
});

it('does not disclose hidden lists in an empty profile overview', function () {
    $owner = socialUser('private_collector');
    RecordList::query()->create(['user_id' => $owner->id, 'name' => 'Secret selection', 'visibility' => 'only_me']);

    $this->get('/private_collector/lists')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('listCount', 0)->has('lists.data', 0))->assertDontSee('Secret selection');
    $this->get('/private_collector')->assertRedirect('/login');
    $this->get('/collections?tab=lists')->assertInertia(fn (Assert $page) => $page->has('lists.data', 0));
});
