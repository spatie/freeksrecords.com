<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('handle', 30)->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('legacy_owner')->default(false);
            $table->string('visibility')->default('only_me');
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('digest_enabled')->default(true);
            $table->date('digest_week')->nullable();
            $table->string('discogs_username')->nullable()->unique();
            $table->text('discogs_token')->nullable();
            $table->text('discogs_secret')->nullable();
            $table->timestamp('collection_imported_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->nullable();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        $freek = DB::table('users')->insertGetId([
            'name' => 'Freek Van der Herten', 'handle' => 'freek', 'email' => 'freek@spatie.be',
            // No known password or public claim endpoint. Ownership is delivered to this inbox.
            'password' => Hash::make(Str::random(64)), 'legacy_owner' => true,
            'visibility' => 'everyone', 'created_at' => now(), 'updated_at' => now(),
            'collection_imported_at' => DB::table('collection_records')->exists() ? now() : null,
        ]);

        // Copy before swapping tables: preserve every copy, payload, date and legacy URL.
        Schema::create('owned_collection_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedBigInteger('discogs_release_id')->index();
            $table->string('added_at')->index();
            $table->json('payload');
            $table->boolean('is_activity')->default(false)->index();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'instance_id']);
        });
        DB::table('collection_records')->orderBy('instance_id')->chunk(100, function ($records) use ($freek): void {
            foreach ($records as $record) {
                DB::table('owned_collection_records')->insert([
                    'user_id' => $freek, 'instance_id' => $record->instance_id,
                    'discogs_release_id' => $record->discogs_release_id,
                    'added_at' => $record->added_at, 'payload' => $record->payload,
                    'updated_at' => $record->updated_at, 'is_activity' => false,
                ]);
            }
        });
        Schema::drop('collection_records');
        Schema::rename('owned_collection_records', 'collection_records');

        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_low', 'user_high']);
        });
        Schema::create('appreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_record_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'collection_record_id']);
        });
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_record_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('social_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('collection_record_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
        });
    }
};
