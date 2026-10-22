<?php

namespace App\Support;

use App\Models\CollectionRecord;
use App\Models\User;
use Illuminate\Http\Request;

class SocialPreview
{
    /** @return array<string, string> */
    public function metadata(Request $request): array
    {
        $preview = [
            'title' => 'Plaat Club',
            'description' => 'Good taste is contagious. Share your record collection, discover your friends’ finds, and keep the conversation spinning.',
            'url' => route('home'),
            'image' => route('socialPreview.home'),
            'imageAlt' => 'Plaat Club. Good taste is contagious.',
            'type' => 'website',
        ];

        $record = $request->route('record');
        if ($record instanceof CollectionRecord && $record->user->canViewCollection(null)) {
            $title = "{$record->payload['displayTitle']} by {$record->payload['artist']}";

            return array_replace($preview, [
                'title' => $title,
                'description' => "{$title}, from {$record->user->name}’s collection on Plaat Club.",
                'url' => route('shelf.record', $record),
                'image' => route('socialPreview.record', $record),
                'imageAlt' => $title,
                'type' => 'music.album',
            ]);
        }

        $user = $request->route('user');
        if ($user instanceof User && $user->canViewCollection(null)) {
            return array_replace($preview, [
                'title' => "{$user->name}’s collection",
                'description' => "Explore @{$user->handle}’s record collection on Plaat Club.",
                'url' => route('profile', $user->handle),
                'image' => route('socialPreview.collection', $user->handle),
                'imageAlt' => "Record sleeves from {$user->name}’s collection on Plaat Club",
            ]);
        }

        return $preview;
    }
}
