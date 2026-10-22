<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $last_sent_at
 * @property ?CarbonImmutable $fulfilled_at
 */
class Invitation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['email', 'token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'last_sent_at' => 'immutable_datetime', 'fulfilled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }
}
