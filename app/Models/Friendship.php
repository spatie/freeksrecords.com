<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_low
 * @property int $user_high
 * @property int $requested_by
 * @property ?string $accepted_at
 */
class Friendship extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];
}
