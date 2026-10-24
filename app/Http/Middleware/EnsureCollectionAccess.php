<?php

namespace App\Http\Middleware;

use App\Models\CollectionRecord;
use App\Models\RecordList;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureCollectionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $request->routeIs('shelf.cover', 'socialPreview.*')) {
            return $next($request);
        }

        $profile = $request->route('user');
        $publicListProfile = $request->routeIs('profile') && $profile instanceof User && ! $request->user()
            && ! $profile->canViewCollection(null)
            && RecordList::visibleTo(null)->where('user_id', $profile->id)->exists();
        if ($profile instanceof User && ! $profile->canViewCollection($request->user()) && ! $request->user() && ! $publicListProfile) {
            return redirect()->guest(route('login'));
        }

        $record = $request->route('record');
        $id = $request->query('record');
        if (! $record instanceof CollectionRecord && $id !== null) {
            abort_unless(is_string($id) && Str::isUuid($id), 404);
            $record = CollectionRecord::query()->where('public_id', $id)->firstOrFail();
        }
        $owner = $record instanceof CollectionRecord ? $record->user : $request->route('user');

        if (! $owner instanceof User || $owner->canViewCollection($request->user())) {
            return $next($request);
        }

        if (! $request->user()) {
            if ($publicListProfile && ! $record instanceof CollectionRecord) {
                return $next($request);
            }

            return redirect()->guest(route('login'));
        }

        if ($record instanceof CollectionRecord) {
            return redirect()->route('profile', $owner->handle);
        }

        return $next($request);
    }
}
