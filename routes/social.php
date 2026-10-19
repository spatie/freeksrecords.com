<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DiscogsConnectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RecordNotesController;
use App\Http\Controllers\SocialController;
use App\Http\Middleware\PrivateResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(PrivateResponse::class)->group(function () {
    Route::get('/community', [HomeController::class, 'intro'])->name('community');
    Route::get('/collections', [HomeController::class, 'collections'])->name('collections');
    Route::get('/people/{user:handle}', [SocialController::class, 'profile']);
    Route::get('/shelves/{record}', [SocialController::class, 'record'])->whereNumber('record')->name('shelf.record');

    Route::middleware('guest')->group(function () {
        Route::view('/register', 'social.auth', ['mode' => 'register'])->name('register');
        Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:6,1');
        Route::view('/login', 'social.auth', ['mode' => 'login'])->name('login');
        Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:6,1');
        Route::view('/forgot-password', 'social.auth', ['mode' => 'forgot'])->name('password.request');
        Route::post('/forgot-password', [AccountController::class, 'forgot'])->middleware('throttle:6,1');
        Route::get('/reset-password/{token}', fn (string $token) => view('social.auth', ['mode' => 'reset', 'token' => $token]))->name('password.reset');
        Route::post('/reset-password', [AccountController::class, 'reset'])->middleware('throttle:6,1');
    });

    Route::middleware(['auth', 'auth.session'])->group(function () {
        Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
        Route::view('/verify-email', 'social.auth', ['mode' => 'verify'])->name('verification.notice');
        Route::get('/verify-email/{id}/{hash}', [AccountController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::post('/email/verification-notification', [AccountController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');

        Route::middleware('verified')->group(function () {
            Route::get('/feed', [SocialController::class, 'feed'])->name('feed');
            Route::get('/friends', [SocialController::class, 'friends'])->name('friends');
            Route::post('/friends/{user}', [SocialController::class, 'friend'])->middleware('throttle:30,1')->name('friend.action');
            Route::view('/settings', 'social.settings')->name('settings');
            Route::patch('/settings', [AccountController::class, 'settings']);
            Route::post('/demo/digest', [SocialController::class, 'previewDigest'])->middleware('throttle:6,1');
            Route::patch('/shelves/{record}/notes', [RecordNotesController::class, 'update'])->whereNumber('record')->name('shelf.notes.update');
            Route::post('/shelves/{record}/appreciations', [SocialController::class, 'appreciate'])->middleware('throttle:60,1');
            Route::delete('/shelves/{record}/appreciations', [SocialController::class, 'unappreciate']);
            Route::post('/shelves/{record}/comments', [SocialController::class, 'comment'])->middleware('throttle:20,1');
            Route::delete('/shelves/{record}/comments/{comment}', [SocialController::class, 'deleteComment']);
            Route::get('/notifications', [SocialController::class, 'notifications'])->name('notifications');
            Route::post('/notifications/read', [SocialController::class, 'readNotifications']);
            Route::post('/discogs/connect', [DiscogsConnectionController::class, 'connect'])->middleware('throttle:6,1');
            Route::get('/discogs/callback', [DiscogsConnectionController::class, 'callback'])->name('discogs.callback');
            Route::get('/discogs/status', [DiscogsConnectionController::class, 'status'])->name('discogs.status');
            Route::post('/discogs/sync', [DiscogsConnectionController::class, 'sync'])->middleware('throttle:3,60');
            Route::delete('/discogs', [DiscogsConnectionController::class, 'disconnect']);
        });
    });

    Route::get('/{user:handle}', [SocialController::class, 'profile'])->where('user', '[a-z0-9_]{3,30}')->name('profile');
});
