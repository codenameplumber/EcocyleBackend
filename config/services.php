<?php

return [

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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        // Both spellings, because the two halves of this project disagreed and
        // the disagreement was invisible: the key lived in .env as RESEND_KEY
        // while this line read RESEND_API_KEY, so config('services.resend.key')
        // was null, AppServiceProvider quietly fell back to the log driver, and
        // a correctly-configured Resend account delivered nothing without ever
        // raising an error. RESEND_API_KEY is the canonical Laravel/Resend name
        // and wins; RESEND_KEY is accepted so the docs and deploy guide work.
        'key' => env('RESEND_API_KEY', env('RESEND_KEY')),
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
