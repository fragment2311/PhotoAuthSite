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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
        'levels' => env('TELEGRAM_LEVELS', 'error,critical,fatal,emergency,alert'),
    ],

    'error_notifications' => [
        'email_to' => env('ERROR_NOTIFICATION_EMAIL_TO', 'access_line@bk.ru'),
        'queue_connection' => env('ERROR_NOTIFICATION_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'queue_name' => env('ERROR_NOTIFICATION_QUEUE_NAME', 'error-notifications'),
        'email_tries' => (int) env('ERROR_NOTIFICATION_EMAIL_TRIES', 5),
        'email_backoff_seconds' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ERROR_NOTIFICATION_EMAIL_BACKOFF_SECONDS', '30,120,300'))
        ))),
        'email_dedupe_seconds' => (int) env('ERROR_NOTIFICATION_EMAIL_DEDUPE_SECONDS', 180),
        'email_rate_limit_per_minute' => (int) env('ERROR_NOTIFICATION_EMAIL_RATE_LIMIT_PER_MINUTE', 30),
        'api_audit_success_sample' => (float) env('API_AUDIT_SUCCESS_SAMPLE', 0.02),
    ],

];
