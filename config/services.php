<?php

return [

    'social_demo' => env('SOCIAL_DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI') ?: rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/auth/google/callback',
    ],

    'discogs' => [
        'username' => env('DISCOGS_USERNAME', 'freekmurze'),
        'token' => env('DISCOGS_TOKEN'),
        'consumer_key' => env('DISCOGS_CONSUMER_KEY'),
        'consumer_secret' => env('DISCOGS_CONSUMER_SECRET'),
        'user_agent' => 'Plaat Club/1.0 (https://plaat.club)',
    ],

    'laravel_cloud' => [
        'purge_token' => env('LARAVEL_CLOUD_PURGE_TOKEN'),
        'environment_id' => env('LARAVEL_CLOUD_ENVIRONMENT_ID'),
    ],

    'musicbrainz' => [
        'user_agent' => 'Plaat Club/1.0 (https://plaat.club)',
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
