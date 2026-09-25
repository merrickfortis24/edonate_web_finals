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

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'security_events_path' => env('FIREBASE_SECURITY_EVENTS_PATH', 'admin_security_events'),
        'web' => [
            'api_key' => env('FIREBASE_WEB_API_KEY'),
            'auth_domain' => env('FIREBASE_WEB_AUTH_DOMAIN'),
            'project_id' => env('FIREBASE_WEB_PROJECT_ID'),
            'app_id' => env('FIREBASE_WEB_APP_ID'),
        ],
        'admin_google_login_enabled' => filter_var(
            env('ADMIN_GOOGLE_LOGIN_ENABLED', 'true'),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'geocoding' => [
        'provider' => env('GEOCODING_PROVIDER', 'nominatim'),
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'eDonate-CapstoneProject/1.0 (fortismerrick@gmail.com)'),
        'timeout' => env('GEOCODING_TIMEOUT', 10),
    ],

    'carto' => [
        'basemap_key' => env('CARTO_BASEMAP_KEY'),
    ],

    'deployment' => [
        'webhook_secret' => env('EDONATE_DEPLOY_WEBHOOK_SECRET'),
    ],

];
