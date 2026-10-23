<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\CollectionBrowseIndex;
use App\Support\DiscogsRecordMapper;
use App\Support\RecordCollection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $instance_id
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property bool $is_activity
 * @property User $user
 * @property Release $release
 * @property int $discogs_release_id
 * @property CarbonImmutable $added_at
 * @property array<string, mixed> $payload
 * @property string|null $shared_note
 * @property string|null $private_note
 */
class CollectionRecord extends Model
{
    use HasPublicId;

    public const CREATED_AT = null;

    protected $guarded = [];

    protected $hidden = ['private_note'];

    protected $with = ['release'];

    /** @var array<string, mixed>|null */
    protected ?array $pendingPayload = null;

    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $hasPayload = $record->pendingPayload !== null;
            if ($hasPayload) {
                $id = (int) ($record->pendingPayload['id'] ?? $record->discogs_release_id);
                $release = Release::storePayload($id, $record->pendingPayload, $record->exists && $record->getOriginal('discogs_release_id') === $id);
                $record->discogs_release_id = $release->id;
                $record->setRelation('release', $release);
                $record->pendingPayload = null;
            } elseif ($record->isDirty('discogs_release_id')) {
                $record->unsetRelation('release');
            }

            if (! $record->exists || $hasPayload || $record->isDirty(['discogs_release_id', 'added_at'])) {
                $record->forceFill(CollectionBrowseIndex::attributes($record->payload, $record->added_at));
            }
        });
        static::saved(function (self $record): void {
            if ($record->wasRecentlyCreated || $record->wasChanged(['user_id', 'discogs_release_id'])) {
                CollectionBrowseIndex::sync([$record]);
            }
        });
    }

    /** @return array<string, mixed> */
    public function getPayloadAttribute(): array
    {
        return $this->pendingPayload ?? [
            'id' => $this->discogs_release_id,
            'instanceId' => $this->instance_id,
            ...$this->release->recordData(),
            'addedAt' => $this->added_at->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function setPayloadAttribute(array $payload): void
    {
        $this->pendingPayload = $payload;
    }

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'discogs_release_id');
    }

    public function url(bool $absolute = true): string
    {
        return route('profile.record', [
            'user' => $this->user->handle,
            'record' => $this->public_id,
            'slug' => app(RecordCollection::class)->slug($this->payload),
        ], $absolute);
    }

    /** @return list<string> */
    public function formats(): array
    {
        return $this->payload['mediaFormats'] ?? app(DiscogsRecordMapper::class)->mediaFormats($this->payload['format'] ?? '');
    }

    /** @param array<string, mixed> $basic */
    public function refreshFormats(array $basic, DiscogsRecordMapper $mapper): bool
    {
        if (! array_key_exists('formats', $basic)) {
            return false;
        }
        foreach ($basic['formats'] as $format) {
            if (empty($format['name'])) {
                return false;
            }
        }
        $release = $this->release;
        $metadata = $mapper->formatMetadata($basic);
        $current = $release->recordData();
        if (array_replace($current, $metadata) === $current) {
            return false;
        }
        $this->setRelation('release', Release::storePayload($release->id, $metadata, replace: true));

        return true;
    }

    /** @return Builder<CollectionRecord> */
    public static function legacy(): Builder
    {
        return self::query()->where('user_id', User::freek()->id);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return Builder<CollectionRecord> */
    public static function visibleTo(?User $viewer): Builder
    {
        return self::query()->whereHas('user', function (Builder $query) use ($viewer): void {
            $query->where('visibility', 'everyone');
            if ($viewer !== null) {
                $query->orWhere('id', $viewer->id)->orWhere(function (Builder $query) use ($viewer): void {
                    $query->where('visibility', 'friends')->whereIn('id', $viewer->friends()->select('id'));
                });
            }
        });
    }

    /** @return Builder<CollectionRecord> */
    public static function friendActivity(User $viewer): Builder
    {
        return self::query()->where('is_activity', true)
            ->whereIn('user_id', $viewer->friends()->whereIn('visibility', ['everyone', 'friends'])->select('id'))
            ->orderByDesc('added_at')->orderByDesc('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_activity' => 'boolean',
            'added_at' => 'immutable_datetime',
        ];
    }
}
