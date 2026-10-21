<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_low
 * @property int $user_high
 * @property int $requested_by
 * @property ?string $accepted_at
 */
class Friendship extends Model
{
    protected $guarded = ['id'];
}
