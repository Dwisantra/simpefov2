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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gitlab' => [
        'url' => env('GITLAB_BASE_URL'),
        'token' => env('GITLAB_TOKEN'),
        'project_id' => env('GITLAB_PROJECT_ID'),
        'labels' => env('GITLAB_ISSUE_LABELS'),
        'webhook_secret' => env('GITLAB_WEBHOOK_SECRET'),
        'default_requester_email' => env('GITLAB_DEFAULT_REQUESTER_EMAIL'),
        'bridge_url' => env('GITLAB_BRIDGE_URL'),
    ],

];
