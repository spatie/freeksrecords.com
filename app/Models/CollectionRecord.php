<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\DiscogsRecordMapper;
use App\Support\RecordCollection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return array<string, mixed> */
    public function getPayloadAttribute(): array
    {
        return [
            'id' => $this->discogs_release_id,
            'instanceId' => $this->instance_id,
            ...$this->release->recordData(),
            'addedAt' => $this->added_at->toIso8601String(),
        ];
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
