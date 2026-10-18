<?php

use App\Models\CollectionRecord;
use App\Models\User;
use App\Support\RecordCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('migrates the existing collection losslessly and silently to Freek’s reserved account', function () {
    foreach (['social_notifications', 'comments', 'appreciations', 'friendships', 'collection_records', 'users', 'password_reset_tokens'] as $table) {
        Schema::drop($table);
    }
    Schema::create('collection_records', function (Blueprint $table) {
        $table->unsignedBigInteger('instance_id')->primary();
        $table->unsignedBigInteger('discogs_release_id');
        $table->string('added_at');
        $table->json('payload');
        $table->timestamp('updated_at')->nullable();
    });
    $records = array_slice(app(RecordCollection::class)->snapshot(), 0, 3);
    foreach ($records as $record) {
        DB::table('collection_records')->insert(['instance_id' => $record['instanceId'], 'discogs_release_id' => $record['id'], 'added_at' => '2026-10-01 12:00:00', 'payload' => json_encode($record, JSON_THROW_ON_ERROR), 'updated_at' => '2026-10-02 12:00:00']);
    }
    $migration = require database_path('migrations/2026_10_18_100000_create_social_collections.php');
    $migration->up();
    $freek = User::freek();
    expect($freek->email)->toBe('freek@spatie.be')->and($freek->visibility)->toBe('everyone')->and($freek->collection_imported_at)->not->toBeNull();
    expect(CollectionRecord::legacy()->count())->toBe(3)->and(CollectionRecord::query()->where('is_activity', true)->count())->toBe(0);
    foreach ($records as $record) {
        $copy = CollectionRecord::legacy()->where('instance_id', $record['instanceId'])->firstOrFail();
        expect($copy->payload)->toBe($record)->and($copy->added_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 12:00:00');
    }
    $this->assertDatabaseCount('social_notifications', 0);
    $this->getJson('/api/records/'.$records[0]['instanceId'])->assertOk()->assertJsonPath('title', $records[0]['title']);
});
