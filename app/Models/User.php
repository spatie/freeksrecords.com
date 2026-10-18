<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $email
 * @property string $password
 * @property bool $legacy_owner
 * @property string $visibility
 * @property bool $comments_enabled
 * @property bool $digest_enabled
 * @property ?string $digest_week
 * @property ?string $discogs_username
 * @property ?string $discogs_token
 * @property ?string $discogs_secret
 * @property ?CarbonImmutable $email_verified_at
 * @property ?CarbonImmutable $collection_imported_at
 * @property ?CarbonImmutable $last_synced_at
 * @property ?string $sync_status
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'email', 'discogs_token', 'discogs_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'email_verified_at' => 'immutable_datetime',
            'legacy_owner' => 'boolean', 'comments_enabled' => 'boolean', 'digest_enabled' => 'boolean',
            'discogs_token' => 'encrypted', 'discogs_secret' => 'encrypted',
            'collection_imported_at' => 'immutable_datetime', 'last_synced_at' => 'immutable_datetime',
        ];
    }

    public static function freek(): self
    {
        return static::query()->where('legacy_owner', true)->firstOrFail();
    }

    /** @return HasMany<CollectionRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(CollectionRecord::class);
    }

    /** @return Builder<User> */
    public function friends(): Builder
    {
        return self::query()->where(function (Builder $query): void {
            $query->whereIn('id', Friendship::query()
                ->whereNotNull('accepted_at')->where('user_low', $this->id)->select('user_high'))
                ->orWhereIn('id', Friendship::query()
                    ->whereNotNull('accepted_at')->where('user_high', $this->id)->select('user_low'));
        });
    }

    public function canViewCollection(?self $viewer): bool
    {
        return $this->visibility === 'everyone' || $viewer?->id === $this->id
            || ($this->visibility === 'friends' && $viewer !== null && $this->friends()->whereKey($viewer->id)->exists());
    }
}
