<?php

namespace App\Http\Controllers;

use App\Models\CollectionRecord;
use App\Models\User;
use App\Support\RecordShareImage;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class SocialPreviewController extends Controller
{
    public function home(RecordShareImage $image): Response
    {
        return $this->image(['home'], fn () => $image->renderClub('GOOD TASTE.', 'is contagious.'));
    }

    public function collection(User $user, RecordShareImage $image): Response
    {
        abort_unless($user->canViewCollection(null), 404);

        $records = array_values($user->records()->orderByDesc('added_at')->orderByDesc('id')->limit(5)->get()
            ->map(fn (CollectionRecord $record) => Arr::only($record->payload, ['cover']))->all());
        $title = "{$user->name}’s collection";
        $subtitle = "@{$user->handle} / Plaat Club";

        return $this->image([$title, $subtitle, $records], fn () => $image->renderClub($title, $subtitle, $records));
    }

    public function record(CollectionRecord $record, RecordShareImage $image): Response
    {
        abort_unless($record->user->canViewCollection(null), 404);

        $fields = Arr::only($record->payload, ['cover', 'artist', 'displayTitle', 'originalYear']);

        return $this->image($fields, fn () => $image->render($fields));
    }

    /**
     * @param  array<int|string, mixed>  $fields
     * @param  Closure(): string  $render
     */
    protected function image(array $fields, Closure $render): Response
    {
        $key = 'social-preview:v2:'.hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));
        $bytes = Cache::remember($key, now()->addDay(), fn () => base64_encode($render()));

        return response(base64_decode($bytes))->header('Content-Type', 'image/jpeg');
    }
}
