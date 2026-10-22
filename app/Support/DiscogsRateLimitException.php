<?php

namespace App\Support;

use RuntimeException;

class DiscogsRateLimitException extends RuntimeException
{
    public function __construct(public int $retryAfter)
    {
        parent::__construct('Discogs request budget is exhausted. Retry later.');
    }
}
