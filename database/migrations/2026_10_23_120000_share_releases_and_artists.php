<?php

use App\Models\CollectionRecord;
use App\Support\CollectionBrowseIndex;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot disable cascading foreign keys inside a transaction.
            Schema::withoutForeignKeyConstraints(fn () => DB::transaction(fn () => $this->shareReleases()));

            return;
        }

        $this->shareReleases();
    }

    protected function shareReleases(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->json('data');
            $table->timestamp('info_imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('artists', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->longText('profile')->nullable();
            $table->timestamps();
        });

        Schema::create('artist_release', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->primary(['artist_id', 'release_id']);
        });

        DB::table('collection_records')->orderBy('id')->chunkById(200, function ($records): void {
            $releases = DB::table('releases')->whereIn('id', $records->pluck('discogs_release_id'))->get()->keyBy('id');
            $payloads = $records->mapWithKeys(fn ($record) => [$record->id => json_decode($record->payload, true, flags: JSON_THROW_ON_ERROR)]);
            $artists = DB::table('artists')->whereIn('id', $payloads->flatMap(fn ($payload) => array_column($payload['artistBios'] ?? [], 'id')))->get()->keyBy('id');
            $releaseRows = [];
            $artistRows = [];
            $links = [];
            $now = now()->toDateTimeString();

            foreach ($records as $record) {
                $payload = $payloads[$record->id];
                $fields = [
                    'artist', 'artists', 'title', 'displayTitle', 'year', 'originalYear',
                    'originalReleaseDate', 'pressingReleaseDate', 'originalDateSource', 'musicbrainzId',
                    'label', 'catalogNumber', 'format', 'edition', 'mediaFormats', 'genres', 'styles',
                    'cover', 'coverSource', 'coverPreview', 'discogsUrl', 'appleMusicUrl', 'appleMusicSearch',
                    'spotifyUrl', 'spotifySearch', 'trackSource', 'tracks', 'releaseNotes', 'credits',
                ];
                $existing = $releaseRows[$record->discogs_release_id] ?? (array) ($releases[$record->discogs_release_id] ?? []);
                $data = Arr::only($payload, $fields);
                if ($existing !== []) {
                    $previous = json_decode($existing['data'], true, flags: JSON_THROW_ON_ERROR);
                    $data = [...$data, ...$previous];
                    foreach (['cover', 'coverSource', 'releaseNotes', 'tracks'] as $field) {
                        if (empty($data[$field]) && ! empty($payload[$field])) {
                            $data[$field] = $payload[$field];
                        }
                    }
                    if (! array_filter($data['credits'] ?? []) && array_filter($payload['credits'] ?? [])) {
                        $data['credits'] = $payload['credits'];
                    }
                    foreach (['spotify', 'appleMusic'] as $service) {
                        if (! empty($previous[$service.'Search']) && isset($payload[$service.'Search']) && ! $payload[$service.'Search']) {
                            $data[$service.'Url'] = $payload[$service.'Url'];
                            $data[$service.'Search'] = false;
                        }
                    }
                    if (str_starts_with($previous['cover'] ?? '', 'https://i.discogs.com/') || ($previous['cover'] ?? '') === '/images/record-placeholder.svg') {
                        if (filled($payload['cover'] ?? null) && ! str_starts_with($payload['cover'], 'https://i.discogs.com/') && $payload['cover'] !== '/images/record-placeholder.svg') {
                            $data['cover'] = $payload['cover'];
                        }
                    }
                    unset($data['coverPreview']);
                    if (($data['cover'] ?? null) === ($previous['cover'] ?? null) && ! empty($previous['coverPreview'])) {
                        $data['coverPreview'] = $previous['coverPreview'];
                    } elseif (($data['cover'] ?? null) === ($payload['cover'] ?? null) && ! empty($payload['coverPreview'])) {
                        $data['coverPreview'] = $payload['coverPreview'];
                    }
                }
                $imported = isset($payload['artistBios'], $payload['releaseNotes'], $payload['credits']);
                $releaseRows[$record->discogs_release_id] = [
                    'id' => $record->discogs_release_id,
                    'data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'info_imported_at' => $existing['info_imported_at'] ?? ($imported ? $now : null),
                    'created_at' => $existing['created_at'] ?? $now, 'updated_at' => $now,
                ];

                foreach ($payload['artistBios'] ?? [] as $position => $bio) {
                    $artist = $artistRows[$bio['id']] ?? (array) ($artists[$bio['id']] ?? []);
                    $artistRows[$bio['id']] = [
                        'id' => $bio['id'], 'name' => $artist['name'] ?? $bio['name'],
                        'profile' => $artist['profile'] ?? $bio['profile'] ?? null,
                        'created_at' => $artist['created_at'] ?? $now, 'updated_at' => $now,
                    ];
                    $links[] = [
                        'artist_id' => $bio['id'], 'release_id' => $record->discogs_release_id, 'position' => $position,
                    ];
                }
            }

            DB::table('releases')->upsert(array_values($releaseRows), ['id'], ['data', 'info_imported_at', 'updated_at']);
            if ($artistRows !== []) {
                DB::table('artists')->upsert(array_values($artistRows), ['id'], ['name', 'profile', 'updated_at']);
                foreach (array_chunk($links, 200) as $chunk) {
                    DB::table('artist_release')->insertOrIgnore($chunk);
                }
            }
        });

        // Reconcile only projections that differ from the chosen shared metadata.
        DB::table('collection_records')->join('releases', 'releases.id', '=', 'collection_records.discogs_release_id')
            ->select('collection_records.*', 'releases.data as release_data')
            ->chunkById(200, function ($records): void {
                $projections = [];
                $memberships = [];
                foreach ($records as $record) {
                    $payload = json_decode($record->payload, true, flags: JSON_THROW_ON_ERROR);
                    $data = json_decode($record->release_data, true, flags: JSON_THROW_ON_ERROR);
                    $attributes = CollectionBrowseIndex::attributes($data, CarbonImmutable::parse($record->added_at));
                    $row = (array) $record;
                    unset($row['release_data']);
                    if ($attributes != Arr::only($row, array_keys($attributes))) {
                        $projections[] = [...$row, ...$attributes];
                    }
                    if (($payload['artists'] ?? []) !== ($data['artists'] ?? []) || ($payload['genres'] ?? []) !== ($data['genres'] ?? [])) {
                        $memberships[] = new CollectionRecord([...$row, 'payload' => $data]);
                    }
                }
                if ($projections !== []) {
                    DB::table('collection_records')->upsert($projections, ['id'], ['browse_title', 'browse_original_year', 'browse_added_day', 'browse_search']);
                }
                if ($memberships !== []) {
                    CollectionBrowseIndex::sync($memberships);
                }
            }, 'collection_records.id', 'id');

        Schema::table('collection_records', function (Blueprint $table) {
            $table->foreign('discogs_release_id')->references('id')->on('releases')->restrictOnDelete();
            $table->dropColumn('payload');
        });

        DB::table('cache')->where('key', 'like', '%record-collection-summaries%')->delete();
    }
};
