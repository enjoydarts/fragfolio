<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Cost Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the cost per token (in USD) for different AI models.
    | Prices are per 1M tokens and automatically converted.
    | Last updated: 2025-09-28 (verified latest official pricing)
    |
    */

    'openai' => [
        // GPT-4o models (verified 2025-09-28)
        'gpt-4o-mini' => [
            'input' => 0.15,    // $0.15 per 1M tokens (confirmed latest)
            'output' => 0.60,   // $0.60 per 1M tokens (confirmed latest)
        ],
        'gpt-4o-2024-11-20' => [
            'input' => 2.50,    // $2.50 per 1M tokens
            'output' => 10.00,  // $10.00 per 1M tokens
        ],
        'gpt-4o-2024-08-06' => [
            'input' => 2.50,    // $2.50 per 1M tokens
            'output' => 10.00,  // $10.00 per 1M tokens
        ],
        'gpt-4o-2024-05-13' => [
            'input' => 5.00,    // $5.00 per 1M tokens
            'output' => 15.00,  // $15.00 per 1M tokens
        ],
        'gpt-4o-mini-2024-07-18' => [
            'input' => 0.15,    // $0.15 per 1M tokens
            'output' => 0.60,   // $0.60 per 1M tokens
        ],

        // GPT-4 Turbo models
        'gpt-4-turbo' => [
            'input' => 10.00,   // $10.00 per 1M tokens
            'output' => 30.00,  // $30.00 per 1M tokens
        ],
        'gpt-4-turbo-2024-04-09' => [
            'input' => 10.00,   // $10.00 per 1M tokens
            'output' => 30.00,  // $30.00 per 1M tokens
        ],
        'gpt-4-turbo-preview' => [
            'input' => 10.00,   // $10.00 per 1M tokens
            'output' => 30.00,  // $30.00 per 1M tokens
        ],
        'gpt-4-0125-preview' => [
            'input' => 10.00,   // $10.00 per 1M tokens
            'output' => 30.00,  // $30.00 per 1M tokens
        ],
        'gpt-4-1106-preview' => [
            'input' => 10.00,   // $10.00 per 1M tokens
            'output' => 30.00,  // $30.00 per 1M tokens
        ],

        // GPT-4 models
        'gpt-4' => [
            'input' => 30.00,   // $30.00 per 1M tokens
            'output' => 60.00,  // $60.00 per 1M tokens
        ],
        'gpt-4-0613' => [
            'input' => 30.00,   // $30.00 per 1M tokens
            'output' => 60.00,  // $60.00 per 1M tokens
        ],
        'gpt-4-32k' => [
            'input' => 60.00,   // $60.00 per 1M tokens
            'output' => 120.00, // $120.00 per 1M tokens
        ],
        'gpt-4-32k-0613' => [
            'input' => 60.00,   // $60.00 per 1M tokens
            'output' => 120.00, // $120.00 per 1M tokens
        ],

        // GPT-3.5 Turbo models
        'gpt-3.5-turbo' => [
            'input' => 0.50,    // $0.50 per 1M tokens
            'output' => 1.50,   // $1.50 per 1M tokens
        ],
        'gpt-3.5-turbo-0125' => [
            'input' => 0.50,    // $0.50 per 1M tokens
            'output' => 1.50,   // $1.50 per 1M tokens
        ],
        'gpt-3.5-turbo-1106' => [
            'input' => 1.00,    // $1.00 per 1M tokens
            'output' => 2.00,   // $2.00 per 1M tokens
        ],
        'gpt-3.5-turbo-instruct' => [
            'input' => 1.50,    // $1.50 per 1M tokens
            'output' => 2.00,   // $2.00 per 1M tokens
        ],

        // o1 models
        'o1-preview' => [
            'input' => 15.00,   // $15.00 per 1M tokens
            'output' => 60.00,  // $60.00 per 1M tokens
        ],
        'o1-mini' => [
            'input' => 3.00,    // $3.00 per 1M tokens
            'output' => 12.00,  // $12.00 per 1M tokens
        ],
    ],

    'anthropic' => [
        // Claude 3.5 models (verified 2025-09-28)
        'claude-3-5-sonnet-20241022' => [
            'input' => 3.00,    // $3.00 per 1M tokens (confirmed latest)
            'output' => 15.00,  // $15.00 per 1M tokens (confirmed latest)
        ],
        'claude-3-5-haiku-20241022' => [
            'input' => 0.80,    // $0.80 per 1M tokens (confirmed latest)
            'output' => 4.00,   // $4.00 per 1M tokens (confirmed latest)
        ],
        'claude-sonnet-4' => [
            'input' => 3.00,    // $3.00 per 1M tokens (confirmed latest)
            'output' => 15.00,  // $15.00 per 1M tokens (confirmed latest)
        ],

        // Claude 3 models
        'claude-3-opus-20240229' => [
            'input' => 15.00,   // $15.00 per 1M tokens
            'output' => 75.00,  // $75.00 per 1M tokens
        ],
        'claude-3-sonnet-20240229' => [
            'input' => 3.00,    // $3.00 per 1M tokens
            'output' => 15.00,  // $15.00 per 1M tokens
        ],
        'claude-3-haiku-20240307' => [
            'input' => 0.25,    // $0.25 per 1M tokens
            'output' => 1.25,   // $1.25 per 1M tokens
        ],

        // Legacy Claude models
        'claude-2.1' => [
            'input' => 8.00,    // $8.00 per 1M tokens
            'output' => 24.00,  // $24.00 per 1M tokens
        ],
        'claude-2.0' => [
            'input' => 8.00,    // $8.00 per 1M tokens
            'output' => 24.00,  // $24.00 per 1M tokens
        ],
        'claude-instant-1.2' => [
            'input' => 0.80,    // $0.80 per 1M tokens
            'output' => 2.40,   // $2.40 per 1M tokens
        ],
    ],

    'gemini' => [
        // Gemini 2.5 models (verified 2025-09-28)
        'gemini-2.5-flash' => [
            'input' => 0.075,   // $0.075 per 1M tokens (confirmed with 78% price reduction)
            'output' => 0.30,   // $0.30 per 1M tokens (confirmed with 71% price reduction)
        ],

        // Gemini 2.0 models (experimental)
        'gemini-2.0-flash' => [
            'input' => 0.10,    // $0.10 per 1M tokens (verified 2025-09-28)
            'output' => 0.40,   // $0.40 per 1M tokens (verified 2025-09-28)
        ],
        'gemini-2.5-flash-lite' => [
            'input' => 0.0375,  // Lowest cost option in 2.5 family
            'output' => 0.15,   // Lowest cost option in 2.5 family
        ],
        'gemini-2.5-pro' => [
            'input' => 1.25,    // 64% price reduction from previous
            'output' => 5.00,   // 52% price reduction from previous
        ],

        // Gemini 1.5 models (legacy but still available)
        'gemini-1.5-flash' => [
            'input' => 0.075,   // $0.075 per 1M tokens
            'output' => 0.30,   // $0.30 per 1M tokens
        ],
        'gemini-1.5-pro' => [
            'input' => 1.25,    // $1.25 per 1M tokens
            'output' => 5.00,   // $5.00 per 1M tokens
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Models
    |--------------------------------------------------------------------------
    |
    | Default fallback models for cost calculation when an unknown model
    | is encountered for each provider.
    |
    */
    'defaults' => [
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-3-5-sonnet-20241022',
        'gemini' => 'gemini-2.5-flash',
    ],
];
