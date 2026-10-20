<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $email
 * @property string $password
 * @property ?string $google_id
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
 * @property ?int $sync_total
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    public const RESERVED_HANDLES = [
        'api', 'auth', 'build', 'collection', 'collections', 'community', 'demo', 'discogs',
        'email', 'favicon', 'feed', 'fonts', 'friends', 'images', 'index', 'login', 'logout',
        'notifications', 'people', 'privacy', 'record', 'register', 'robots', 'settings', 'share',
        'shelves', 'storage', 'terms', 'up',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'email', 'google_id', 'discogs_token', 'discogs_secret'];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $localPart = Str::before($user->email, '@');
            if (blank($user->name)) {
                $user->name = (string) Str::of($localPart)->replaceMatches('/[^\pL\pN]+/u', ' ')->headline()->trim()->substr(0, 80);
                $user->name = $user->name ?: 'Collector';
            }

            if (filled($user->handle)) {
                return;
            }

            $base = (string) Str::of($localPart)->ascii()->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->substr(0, 30);
            $base = strlen($base) < 3 ? "collector_{$base}" : $base;
            $user->handle = $base;

            while (in_array($user->handle, self::RESERVED_HANDLES, true) || self::query()->where('handle', $user->handle)->exists()) {
                $user->handle = Str::substr($base, 0, 21).'_'.Str::lower(Str::random(8));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'email_verified_at' => 'immutable_datetime',
            'legacy_owner' => 'boolean', 'comments_enabled' => 'boolean', 'digest_enabled' => 'boolean',
            'discogs_token' => 'encrypted', 'discogs_secret' => 'encrypted',
            'collection_imported_at' => 'immutable_datetime', 'last_synced_at' => 'immutable_datetime',
            'sync_total' => 'integer',
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
