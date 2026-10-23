<?php

namespace App\Http\Controllers;

use App\Support\RecordCollection;
use App\Support\RecordReleaseDates;
use Illuminate\Http\JsonResponse;

class RecordDetailsController extends Controller
{
    public function __invoke(string $instanceId, RecordCollection $collection, RecordReleaseDates $dates): JsonResponse
    {
        $record = $collection->findByPublicId($instanceId);

        abort_if($record === null, 404);

        return response()->json($dates->enrichOne($record));
    }
}
