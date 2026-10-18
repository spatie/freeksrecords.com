<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $actor_id
 * @property ?int $collection_record_id
 * @property string $kind
 * @property ?string $read_at
 * @property User $actor
 * @property ?CollectionRecord $record
 */
class SocialNotification extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<CollectionRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(CollectionRecord::class, 'collection_record_id');
    }

    /** @return Builder<SocialNotification> */
    public static function visibleTo(User $user): Builder
    {
        return self::query()->where('user_id', $user->id)->where(function (Builder $query) use ($user): void {
            $query->whereNull('collection_record_id')
                ->orWhereIn('collection_record_id', CollectionRecord::visibleTo($user)->select('id'));
        });
    }

    public static function send(User $recipient, User $actor, string $kind, ?CollectionRecord $record = null): void
    {
        if ($recipient->id !== $actor->id) {
            static::query()->create([
                'user_id' => $recipient->id, 'actor_id' => $actor->id,
                'kind' => $kind, 'collection_record_id' => $record?->id,
            ]);
        }
    }
}
