<?php

namespace App\Support;

use RuntimeException;

class MusicBrainzRateLimitException extends RuntimeException
{
    public function __construct(public int $retryAfter)
    {
        parent::__construct('MusicBrainz is cooling down. Retry later.');
    }
}
