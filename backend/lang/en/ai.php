<?php

return [
    // Validation messages
    'validation' => [
        'validation_failed' => 'Validation failed',
        'query_required' => 'Query is required',
        'query_min_length' => 'Query must be at least 2 characters long',
        'query_max_length' => 'Query must not exceed 100 characters',
        'type_required' => 'Type is required',
        'type_invalid' => 'Type must be either brand or fragrance',
        'limit_integer' => 'Limit must be a number',
        'limit_min' => 'Limit must be at least 1',
        'limit_max' => 'Limit must not exceed 20',
        'language_invalid' => 'Language must be either ja or en',
        'provider_invalid' => 'Provider must be either openai or anthropic',
        'queries_required' => 'Queries array is required',
        'queries_array' => 'Queries must be an array',
        'queries_min' => 'At least 1 query is required',
        'queries_max' => 'Maximum 10 queries are allowed',
    ],

    // Error messages
    'errors' => [
        'completion_failed' => 'Completion process failed',
        'batch_completion_failed' => 'Batch completion process failed',
        'normalization_failed' => 'Normalization process failed',
        'notes_suggestion_failed' => 'Notes suggestion process failed',
        'attributes_suggestion_failed' => 'Attributes suggestion process failed',
        'providers_fetch_failed' => 'Failed to fetch provider information',
        'health_check_failed' => 'Health check failed',
        'provider_unavailable' => 'The specified provider is not available',
        'daily_limit_exceeded' => 'Daily usage limit exceeded',
        'monthly_limit_exceeded' => 'Monthly usage limit exceeded',
        'rate_limit_exceeded' => 'Rate limit exceeded. Please wait before making another request',
        'cost_tracking_failed' => 'Failed to record cost tracking',
    ],

    // Success messages
    'success' => [
        'completion_successful' => 'Completion completed successfully',
        'normalization_successful' => 'Normalization completed successfully',
        'notes_suggestion_successful' => 'Notes suggestion completed successfully',
        'attributes_suggestion_successful' => 'Attributes suggestion completed successfully',
    ],

    // Operation types
    'operation_types' => [
        'completion' => 'Completion',
        'normalization' => 'Normalization',
        'notes_suggestion' => 'Notes Suggestion',
        'attributes_suggestion' => 'Attributes Suggestion',
    ],

    // Provider names
    'providers' => [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
    ],

    // Confidence levels
    'confidence_levels' => [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],

    // Intensity levels
    'intensity_levels' => [
        'strong' => 'Strong',
        'moderate' => 'Moderate',
        'light' => 'Light',
    ],

    // Seasons
    'seasons' => [
        'spring' => 'Spring',
        'summer' => 'Summer',
        'autumn' => 'Autumn',
        'winter' => 'Winter',
    ],

    // Occasions
    'occasions' => [
        'business' => 'Business',
        'casual' => 'Casual',
        'formal' => 'Formal',
        'date' => 'Date',
        'evening' => 'Evening',
        'sport' => 'Sport',
    ],

    // Time of day
    'time_of_day' => [
        'morning' => 'Morning',
        'daytime' => 'Daytime',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
        'night' => 'Night',
    ],

    // Age groups
    'age_groups' => [
        '10s' => 'Teens',
        '20s' => '20s',
        '30s' => '30s',
        '40s' => '40s',
        '50s' => '50s+',
    ],
];