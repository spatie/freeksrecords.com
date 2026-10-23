<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'artist' => 'artist', 'title' => 'title', 'displayTitle' => 'display_title',
            'year' => 'year', 'originalYear' => 'original_year',
            'originalReleaseDate' => 'original_release_date', 'pressingReleaseDate' => 'pressing_release_date',
            'originalDateSource' => 'original_date_source', 'musicbrainzId' => 'musicbrainz_id',
            'discogsMasterId' => 'discogs_master_id', 'discogsLabelId' => 'discogs_label_id',
            'label' => 'label', 'catalogNumber' => 'catalog_number', 'format' => 'format', 'edition' => 'edition',
            'cover' => 'cover', 'coverSource' => 'cover_source', 'coverPreview' => 'cover_preview',
            'discogsUrl' => 'discogs_url', 'appleMusicUrl' => 'apple_music_url', 'appleMusicSearch' => 'apple_music_search',
            'spotifyUrl' => 'spotify_url', 'spotifySearch' => 'spotify_search',
            'trackSource' => 'track_source', 'releaseNotes' => 'release_notes',
        ];

        Schema::table('releases', function (Blueprint $table): void {
            foreach (['artist', 'title', 'display_title', 'label', 'catalog_number', 'format', 'edition',
                'cover', 'cover_source', 'cover_preview', 'discogs_url', 'apple_music_url', 'spotify_url'] as $column) {
                $table->text($column)->nullable();
            }
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->unsignedSmallInteger('original_year')->nullable()->index();
            $table->string('original_release_date', 10)->nullable();
            $table->string('pressing_release_date', 10)->nullable();
            $table->string('original_date_source')->nullable();
            $table->string('musicbrainz_id', 36)->nullable()->index();
            $table->unsignedBigInteger('discogs_master_id')->nullable()->index();
            $table->unsignedBigInteger('discogs_label_id')->nullable()->index();
            $table->boolean('apple_music_search')->nullable();
            $table->boolean('spotify_search')->nullable();
            $table->string('track_source')->nullable();
            $table->longText('release_notes')->nullable();
        });

        DB::table('releases')->orderBy('id')->chunkById(200, function ($releases) use ($columns): void {
            $rows = [];
            foreach ($releases as $release) {
                $data = json_decode($release->data, true, flags: JSON_THROW_ON_ERROR);
                $row = ['id' => $release->id];
                foreach ($columns as $field => $column) {
                    $row[$column] = $data[$field] ?? null;
                }
                $row['data'] = json_encode(Arr::except($data, array_keys($columns)), JSON_THROW_ON_ERROR);
                $rows[] = $row;
            }
            DB::table('releases')->upsert($rows, ['id'], ['data', ...array_values($columns)]);
        });

        foreach (['artist', 'title', 'label', 'catalog_number'] as $column) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("CREATE INDEX releases_{$column}_index ON releases ({$column}(191))");
            } else {
                Schema::table('releases', fn (Blueprint $table) => $table->index($column));
            }
        }

        // Existing information stays intact; the next import also fetches the new Discogs identifiers.
        DB::table('releases')->update(['info_imported_at' => null]);
    }
};
