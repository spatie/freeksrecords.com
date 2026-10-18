<?php

namespace App\Mail;

use App\Models\CollectionRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;

class FriendsWeeklyDigest extends Mailable
{
    /** @param Collection<int, CollectionRecord> $records */
    public function __construct(public Collection $records) {}

    public function build(): self
    {
        return $this->subject('This week on your friends’ shelves')->view('mail.friends-digest');
    }
}
