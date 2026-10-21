<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use App\Support\RecordCoverImages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class RecordCoverController extends Controller
{
    public function __invoke(Request $request, CollectionRecord $record, RecordCoverImages $images): Response
    {
        abort_unless($record->user->canViewCollection($request->user()), 404);

        $source = $record->payload['cover'];
        $encoded = Cache::remember('record-cover:'.hash('sha256', $source), now()->addDay(), fn () => base64_encode($images->read($source)));

        return response(base64_decode($encoded))->header('Content-Type', 'image/jpeg');
    }
}
