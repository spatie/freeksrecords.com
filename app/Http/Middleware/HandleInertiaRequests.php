<?php

namespace App\Http\Middleware;

use App\Models\CollectionRecord;
use App\Models\SocialNotification;
use App\Models\User;
use App\Support\SocialData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        $manifest = public_path('build/manifest.json');
        if (file_exists($manifest)) {
            // A stable asset origin must not hide a new deployment from open tabs.
            return hash_file('xxh128', $manifest) ?: null;
        }

        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        if (! in_array(PrivateResponse::class, $request->route()?->gatherMiddleware() ?? [], true)) {
            return [];
        }

        return [
            ...parent::share($request),
            'auth' => ['user' => fn () => $request->user() ? SocialData::viewer($request->user()) : null],
            'csrfToken' => fn () => $request->session()->token(),
            'googleEnabled' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
            'notificationCount' => fn () => $request->user()
                ? SocialNotification::visibleTo($request->user())->whereNull('read_at')->count()
                : 0,
            'flash' => ['status' => fn () => $request->session()->get('status')],
            'demoUsers' => fn () => $this->demoUsers($request),
            'recordOverlay' => function () use ($request): ?array {
                $id = $request->query('record');
                if ($id === null) {
                    return null;
                }
                abort_unless(is_string($id) && ctype_digit($id), 404);

                return SocialData::record($request, CollectionRecord::query()->findOrFail($id));
            },
        ];
    }

    /** @return list<array<string, string>> */
    protected function demoUsers(Request $request): array
    {
        if (! $request->routeIs('login') || ! app()->environment('local') || ! config('services.social_demo') || ! Route::has('loginLinkLogin')) {
            return [];
        }

        $existing = User::query()->whereIn('email', array_keys(config('login-link.demo_users')))->pluck('email');
        $users = [];
        foreach (config('login-link.demo_users') as $email => $label) {
            if ($existing->contains($email)) {
                $users[] = ['email' => $email, 'label' => $label, 'url' => route('loginLinkLogin', absolute: false)];
            }
        }

        return $users;
    }
}
