<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $instance_id
 * @property int $id
 * @property int $user_id
 * @property bool $is_activity
 * @property User $user
 * @property int $discogs_release_id
 * @property CarbonImmutable $added_at
 * @property array<string, mixed> $payload
 */
class CollectionRecord extends Model
{
    public const CREATED_AT = null;

    protected $guarded = [];

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
        return static::visibleTo($viewer)->where('is_activity', true)
            ->whereIn('user_id', $viewer->friends()->select('id'))
            ->orderByDesc('added_at')->orderByDesc('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_activity' => 'boolean',
            'added_at' => 'immutable_datetime',
        ];
    }
}
