<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecordNotesController extends Controller
{
    public function update(Request $request, CollectionRecord $record): RedirectResponse
    {
        abort_unless($request->user()->id === $record->user_id, 403);

        $data = $request->validate([
            'shared_note' => 'present|nullable|string|max:5000',
            'private_note' => 'present|nullable|string|max:5000',
        ]);
        $record->update($data);

        return back(fallback: route('shelf.record', $record))->with('status', 'Owner notes saved.');
    }
}
