<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property ?string $profile
 */
class Artist extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    /** @return BelongsToMany<Release, $this> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class);
    }
}
