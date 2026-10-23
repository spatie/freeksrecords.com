<?php

namespace App\Actions\Collections;

use App\Models\Artist;
use App\Models\Release;
use App\Support\CollectionBrowseIndex;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreReleaseAction
{
    /** @param array<string, mixed> $payload */
    public function execute(int $id, array $payload, bool $replace = false): Release
    {
        return DB::transaction(function () use ($id, $payload, $replace): Release {
            $attributes = ['data' => Arr::only($payload, Release::JSON_FIELDS)];
            foreach (Release::PAYLOAD_COLUMNS as $field => $column) {
                if (array_key_exists($field, $payload)) {
                    $attributes[$column] = $payload[$field];
                }
            }
            Release::query()->firstOrCreate(['id' => $id], $attributes);
            $release = Release::query()->lockForUpdate()->findOrFail($id);
            $before = $release->recordData();
            $release->data = $replace
                ? [...$release->data, ...$attributes['data']]
                : [...$attributes['data'], ...$release->data];
            foreach (Arr::except($attributes, ['data']) as $column => $value) {
                if ($replace || $release->getAttribute($column) === null) {
                    $release->setAttribute($column, $value);
                }
            }

            if (array_key_exists('artistBios', $payload)) {
                $this->storeArtistBios($release, $payload['artistBios']);
                if (array_key_exists('releaseNotes', $payload) && array_key_exists('credits', $payload)) {
                    $release->info_imported_at ??= now();
                }
            }

            if ($release->isDirty()) {
                $release->save();
            }

            $after = $release->recordData();
            $attributes = Arr::except(CollectionBrowseIndex::attributes($after, CarbonImmutable::now()), ['browse_added_day']);
            if ($attributes !== Arr::except(CollectionBrowseIndex::attributes($before, CarbonImmutable::now()), ['browse_added_day'])) {
                $release->records()->toBase()->update($attributes);
            }
            if (($before['artists'] ?? []) !== ($after['artists'] ?? []) || ($before['genres'] ?? []) !== ($after['genres'] ?? [])) {
                $release->records()->chunkById(200, fn ($copies) => CollectionBrowseIndex::sync($copies));
            }

            return $release;
        });
    }

    /** @param list<array<string, mixed>> $bios */
    protected function storeArtistBios(Release $release, array $bios): void
    {
        $links = [];

        foreach ($bios as $position => $bio) {
            $artist = Artist::query()->firstOrCreate(['id' => $bio['id']], ['name' => $bio['name'], 'profile' => $bio['profile'] ?? null]);
            if ($artist->profile === null && array_key_exists('profile', $bio)) {
                $artist->update(['profile' => $bio['profile']]);
            }
            $links[$artist->id] = ['position' => $position];
        }

        $release->artists()->syncWithoutDetaching($links);
        $release->unsetRelation('artists');
    }
}
