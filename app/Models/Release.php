<?php

namespace App\Models;

use App\Support\CollectionBrowseIndex;
use App\Support\RecordCollection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property array<string, mixed> $data
 * @property ?CarbonImmutable $info_imported_at
 */
class Release extends Model
{
    public const DATA_FIELDS = [
        'artist', 'artists', 'title', 'displayTitle', 'year', 'originalYear',
        'originalReleaseDate', 'pressingReleaseDate', 'originalDateSource', 'musicbrainzId',
        'label', 'catalogNumber', 'format', 'edition', 'mediaFormats', 'genres', 'styles',
        'cover', 'coverSource', 'coverPreview', 'discogsUrl', 'appleMusicUrl', 'appleMusicSearch',
        'spotifyUrl', 'spotifySearch', 'trackSource', 'tracks', 'releaseNotes', 'credits',
    ];

    public $incrementing = false;

    protected $guarded = [];

    protected $with = ['artists'];

    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }

    protected static function booted(): void
    {
        static::saved(function (self $release): void {
            if (! $release->wasChanged('data')) {
                return;
            }

            $before = $release->getOriginal('data') ?? [];
            $attributes = Arr::except(CollectionBrowseIndex::attributes($release->data, CarbonImmutable::now()), ['browse_added_day']);
            if ($attributes !== Arr::except(CollectionBrowseIndex::attributes($before, CarbonImmutable::now()), ['browse_added_day'])) {
                $release->records()->toBase()->update($attributes);
            }
            if (($before['artists'] ?? []) !== ($release->data['artists'] ?? []) || ($before['genres'] ?? []) !== ($release->data['genres'] ?? [])) {
                $release->records()->chunkById(200, fn ($copies) => CollectionBrowseIndex::sync($copies));
            }

            app(RecordCollection::class)->flush();
        });
    }

    /** @return HasMany<CollectionRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(CollectionRecord::class, 'discogs_release_id');
    }

    /** @return BelongsToMany<Artist, $this> */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('position')->orderByPivot('position');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function storePayload(int $id, array $payload, bool $replace = false): self
    {
        return DB::transaction(function () use ($id, $payload, $replace): self {
            $data = Arr::only($payload, self::DATA_FIELDS);
            $release = self::query()->firstOrCreate(['id' => $id], ['data' => $data]);
            $release = self::query()->lockForUpdate()->findOrFail($id);
            $release->data = $replace ? [...$release->data, ...$data] : [...$data, ...$release->data];

            if (array_key_exists('artistBios', $payload)) {
                $release->storeArtistBios($payload['artistBios']);
                if (array_key_exists('releaseNotes', $payload) && array_key_exists('credits', $payload)) {
                    $release->info_imported_at ??= now();
                }
            }

            if ($release->isDirty()) {
                $release->save();
            }

            return $release;
        });
    }

    /** @param list<array<string, mixed>> $bios */
    public function storeArtistBios(array $bios): void
    {
        $links = [];

        foreach ($bios as $position => $bio) {
            $artist = Artist::query()->firstOrCreate(['id' => $bio['id']], ['name' => $bio['name'], 'profile' => $bio['profile'] ?? null]);
            if ($artist->profile === null && array_key_exists('profile', $bio)) {
                $artist->update(['profile' => $bio['profile']]);
            }
            $links[$artist->id] = ['position' => $position];
        }

        $this->artists()->syncWithoutDetaching($links);
        $this->unsetRelation('artists');
    }

    /** @return array<string, mixed> */
    public function recordData(): array
    {
        $data = $this->data;
        if ($this->info_imported_at !== null || $this->artists->isNotEmpty()) {
            $data['artistBios'] = $this->artists->map(fn (Artist $artist): array => [
                'id' => $artist->id, 'name' => $artist->name, 'profile' => $artist->profile ?? '',
                'discogsUrl' => "https://www.discogs.com/artist/{$artist->id}",
            ])->all();
        }

        return $data;
    }

    protected function casts(): array
    {
        return ['data' => 'array', 'info_imported_at' => 'immutable_datetime'];
    }
}
