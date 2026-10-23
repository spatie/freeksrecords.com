<?php

namespace App\Support;

use App\Models\CollectionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectionBrowseIndex
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function attributes(array $payload, CarbonImmutable $addedAt): array
    {
        return [
            'browse_title' => $payload['title'] ?? '',
            'browse_original_year' => ($payload['originalYear'] ?? null) ?: null,
            'browse_added_day' => $addedAt->setTimezone('Europe/Brussels')->toDateString(),
            'browse_search' => Str::lower(Str::ascii(implode(' ', [
                $payload['artist'] ?? '', $payload['title'] ?? '', $payload['label'] ?? '', $payload['year'] ?? '',
                ...($payload['genres'] ?? []), ...($payload['styles'] ?? []), ...array_column($payload['tracks'] ?? [], 'title'),
            ]))),
        ];
    }

    /** @param iterable<CollectionRecord> $copies */
    public static function sync(iterable $copies): void
    {
        $ids = [];
        $entries = [];
        foreach ($copies as $copy) {
            $ids[] = $copy->id;
            foreach (['artist' => $copy->payload['artists'] ?? [], 'genre' => ($copy->payload['genres'] ?? []) ?: ['Unclassified']] as $kind => $names) {
                foreach (array_unique($names) as $name) {
                    $entries[] = ['collection_record_id' => $copy->id, 'user_id' => $copy->user_id, 'kind' => $kind, 'name' => (string) $name];
                }
            }
        }

        DB::table('collection_browse_entries')->whereIn('collection_record_id', $ids)->delete();
        foreach (array_chunk($entries, 100) as $chunk) {
            DB::table('collection_browse_entries')->insert($chunk);
        }
    }
}
