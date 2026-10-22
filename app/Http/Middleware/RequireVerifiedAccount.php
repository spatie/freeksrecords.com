<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasVerifiedEmail()
            && ! $request->routeIs('verification.*', 'logout', 'login', 'register', 'password.*', 'google.*', 'about', 'privacy', 'terms')) {
            abort_if($request->expectsJson(), 403, 'Your email address is not verified.');

            return redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
