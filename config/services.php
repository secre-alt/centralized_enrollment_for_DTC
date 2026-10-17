<?php

return [

    'application_status_lookup' => [
        'per_ip' => 20,
        'per_identity' => 5,
    ],

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
    'recaptcha' => [
        // Enabled by default in production; local and testing stay usable
        // without external CAPTCHA credentials unless explicitly enabled.
        'enabled'    => env('RECAPTCHA_ENABLED', env('APP_ENV') === 'production'),
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'login_rate_limit' => [
        'per_identity_attempts' => env('LOGIN_RATE_LIMIT_PER_IDENTITY', 5),
        'per_ip_attempts' => env('LOGIN_RATE_LIMIT_PER_IP', 300),
        'decay_seconds' => env('LOGIN_RATE_LIMIT_DECAY_SECONDS', 300),
    ],
        
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

];
