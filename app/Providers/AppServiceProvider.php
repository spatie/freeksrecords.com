<?php

namespace App\Providers;

use App\Listeners\FulfillInvitationsListener;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        Event::listen(Registered::class, [FulfillInvitationsListener::class, 'fulfill']);
        Event::listen(Verified::class, [FulfillInvitationsListener::class, 'fulfill']);

        DB::prohibitDestructiveCommands(app()->isProduction());

        RateLimiter::for('shareImages', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
    }
}
