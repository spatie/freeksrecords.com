<?php

namespace App\Models;

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
 * @property int $user_id
 * @property bool $is_activity
 * @property User $user
 * @property int $discogs_release_id
 * @property CarbonImmutable $added_at
 * @property array<string, mixed> $payload
 * @property string|null $shared_note
 * @property string|null $private_note
 */
class CollectionRecord extends Model
{
    public const CREATED_AT = null;

    protected $guarded = [];

    protected $hidden = ['private_note'];

    public function url(bool $absolute = true): string
    {
        return route('profile.record', [
            'user' => $this->user->handle,
            'record' => $this->id,
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
        $this->payload = array_replace($this->payload, $mapper->formatMetadata($basic));
        if (! $this->isDirty('payload')) {
            return false;
        }
        $this->save();

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
