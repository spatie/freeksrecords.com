<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoLoginLinks
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment('local') && config('services.social_demo'), 404);

        $email = $request->input('email');
        abort_unless(is_string($email) && array_key_exists($email, config('login-link.demo_users'))
            && User::query()->where('email', $email)->exists(), 404);

        // Package fields can otherwise override the chosen email, model or guard.
        // Explicit POST values also take precedence over forged query parameters.
        $request->merge([
            'email' => $email,
            'key' => null,
            'user_attributes' => '[]',
            'user_model' => User::class,
            'guard' => 'web',
            'redirect_url' => '/friends',
        ]);

        return $next($request);
    }
}
