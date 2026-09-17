<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
    ],

    'ga4' => [
        // Google Analytics 4 measurement id (G-XXXXXXX). Empty = no tag rendered.
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
    ],

    'x' => [
        // Keywords ORed into the "find announcements on X" search link for a channel.
        'search_keywords' => array_values(array_filter(array_map('trim', explode(',', env('X_SEARCH_KEYWORDS', '予定,配信,朝活,告知'))))),
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
        // How many days of already-ended streams to import so a freshly added
        // channel shows recent history on the board.
        'backfill_days' => (int) env('STREAMS_BACKFILL_DAYS', 14),
    ],

];
