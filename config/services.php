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

    'ngx' => [
        'mode' => env('NGX_MODE', 'test'), // live | test | dummy
        'simulate_errors' => env('NGX_SIMULATE_ERRORS', false),
    ],

    'qoreid' => [
        'base_url' => env('QOREID_BASE_URL', 'https://api.qoreid.com'),
        'client_id' => env('QOREID_CLIENT_ID'),
        'client_secret' => env('QOREID_CLIENT_SECRET'),
        'api_key' => env('QOREID_API_KEY'),
        'dummy_mode' => env('QOREID_DUMMY_MODE', false),
    ],

    'tatum' => [
        'base_url' => env('TATUM_BASE_URL', 'https://api.tatum.io/v3'),
        'api_key' => env('TATUM_API_KEY'),
    ],

    'cscs' => [
        'simulate_errors' => env('CSCS_SIMULATE_ERRORS', false),
    ],

    'crypto' => [
        'api_key' => env('TATUM_API_KEY'),
        'base_url' => env('TATUM_BASE_URL', 'https://api.tatum.io'),
        'webhook_secret' => env('TATUM_WEBHOOK_SECRET'),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'callback_url' => env('PAYSTACK_CALLBACK_URL'),
    ],

];
