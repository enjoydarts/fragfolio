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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'gemini' => [
        'project_id' => env('GEMINI_PROJECT_ID'),
        'location' => env('GEMINI_LOCATION', 'asia-northeast1'),
        'service_account_path' => env('GEMINI_SERVICE_ACCOUNT_PATH'),
    ],

    'ai' => [
        'default_provider' => env('DEFAULT_AI_PROVIDER', 'gemini'),
        'gpt_model' => env('AI_MODEL_GPT', 'gpt-4o-mini'),
        'claude_model' => env('AI_MODEL_CLAUDE', 'claude-3-5-haiku-20241022'),
        'gemini_model' => env('AI_MODEL_GEMINI', 'gemini-2.5-flash'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
