<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/** @property string $public_id */
class Activity extends BaseActivity
{
    use HasPublicId;
}
