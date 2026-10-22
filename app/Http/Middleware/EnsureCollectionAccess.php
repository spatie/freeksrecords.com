<?php

namespace App\Http\Middleware;

use App\Models\CollectionRecord;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCollectionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $request->routeIs('shelf.cover', 'socialPreview.*')) {
            return $next($request);
        }

        $profile = $request->route('user');
        if ($profile instanceof User && ! $profile->canViewCollection($request->user()) && ! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $record = $request->routeIs('shelf.record') ? $request->route('record') : null;
        $id = $request->query('record');
        if (! $record instanceof CollectionRecord && is_string($id) && ctype_digit($id)) {
            $record = CollectionRecord::query()->findOrFail($id);
        }
        $owner = $record instanceof CollectionRecord ? $record->user : $request->route('user');

        if (! $owner instanceof User || $owner->canViewCollection($request->user())) {
            return $next($request);
        }

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if ($record instanceof CollectionRecord) {
            return redirect()->route('profile', $owner->handle);
        }

        return $next($request);
    }
}
