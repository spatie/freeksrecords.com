<?php

namespace App\Http\Controllers;

use App\Support\RecordCollection;
use App\Support\StoredShareImages;
use Illuminate\Http\RedirectResponse;

class RecordShareImageController extends Controller
{
    public function __invoke(string $instanceId, RecordCollection $collection, StoredShareImages $shareImages): RedirectResponse
    {
        $copy = $collection->findByPublicId($instanceId);
        $record = $copy ? $collection->findSummary($copy['instanceId']) : null;

        abort_if($record === null, 404);

        return redirect()
            ->away($shareImages->recordUrl($record))
            ->setCache(['public' => true, 'max_age' => 3600, 's_maxage' => 3600]);
    }
}
