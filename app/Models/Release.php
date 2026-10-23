<?php

namespace App\Models;

use App\Support\CollectionBrowseIndex;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property array<string, mixed> $data
 * @property ?string $cover
 * @property ?string $cover_preview
 * @property ?CarbonImmutable $info_imported_at
 */
class Release extends Model
{
    public const PAYLOAD_COLUMNS = [
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

    public const JSON_FIELDS = ['artists', 'labels', 'mediaFormats', 'genres', 'styles', 'tracks', 'credits'];

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
            if (! $release->wasChanged(['data', ...array_values(self::PAYLOAD_COLUMNS)])) {
                return;
            }

            $before = $release->metadata(original: true);
            $after = $release->metadata();
            $attributes = Arr::except(CollectionBrowseIndex::attributes($after, CarbonImmutable::now()), ['browse_added_day']);
            if ($attributes !== Arr::except(CollectionBrowseIndex::attributes($before, CarbonImmutable::now()), ['browse_added_day'])) {
                $release->records()->toBase()->update($attributes);
            }
            if (($before['artists'] ?? []) !== ($after['artists'] ?? []) || ($before['genres'] ?? []) !== ($after['genres'] ?? [])) {
                $release->records()->chunkById(200, fn ($copies) => CollectionBrowseIndex::sync($copies));
            }
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
            $attributes = ['data' => Arr::only($payload, self::JSON_FIELDS)];
            foreach (self::PAYLOAD_COLUMNS as $field => $column) {
                if (array_key_exists($field, $payload)) {
                    $attributes[$column] = $payload[$field];
                }
            }
            $release = self::query()->firstOrCreate(['id' => $id], $attributes);
            $release = self::query()->lockForUpdate()->findOrFail($id);
            $release->data = $replace
                ? [...$release->data, ...$attributes['data']]
                : [...$attributes['data'], ...$release->data];
            foreach (Arr::except($attributes, ['data']) as $column => $value) {
                if ($replace || $release->getAttribute($column) === null) {
                    $release->setAttribute($column, $value);
                }
            }

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
        $data = $this->metadata();
        if ($this->info_imported_at !== null || $this->artists->isNotEmpty()) {
            $data['artistBios'] = $this->artists->map(fn (Artist $artist): array => [
                'id' => $artist->id, 'name' => $artist->name, 'profile' => $artist->profile ?? '',
                'discogsUrl' => "https://www.discogs.com/artist/{$artist->id}",
            ])->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function metadata(bool $original = false): array
    {
        $data = ($original ? $this->getOriginal('data') : $this->data) ?? [];
        foreach (self::PAYLOAD_COLUMNS as $field => $column) {
            $value = $original ? $this->getOriginal($column) : $this->getAttribute($column);
            if ($value !== null || in_array($field, ['originalYear', 'originalReleaseDate', 'pressingReleaseDate', 'originalDateSource', 'musicbrainzId'], true)) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    protected function casts(): array
    {
        return [
            'data' => 'array', 'info_imported_at' => 'immutable_datetime',
            'year' => 'integer', 'original_year' => 'integer',
            'discogs_master_id' => 'integer', 'discogs_label_id' => 'integer',
            'apple_music_search' => 'boolean', 'spotify_search' => 'boolean',
        ];
    }
}
