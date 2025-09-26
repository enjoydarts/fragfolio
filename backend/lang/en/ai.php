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
        'batch_normalization_failed' => 'Batch normalization process failed',
        'suggestion_failed' => 'Note suggestion process failed',
        'batch_suggestion_failed' => 'Batch note suggestion process failed',
        'feedback_failed' => 'Feedback processing failed',
        'provider_list_failed' => 'Failed to get provider list',
        'categories_failed' => 'Failed to get note categories',
        'similar_search_failed' => 'Similar fragrance search failed',
        'limit_exceeded' => 'Usage limit exceeded',
        'batch_limit_exceeded' => 'Batch processing limit exceeded',
        'batch_size_exceeded' => 'Batch size limit exceeded (maximum 20 items)',
        'internal_error' => 'Internal error occurred',
        'invalid_input' => 'Invalid input provided',
        'cost_usage_failed' => 'Failed to get cost usage information',
        'limit_check_failed' => 'Failed to check usage limits',
        'pattern_analysis_failed' => 'Failed to analyze usage patterns',
        'efficiency_analysis_failed' => 'Failed to analyze cost efficiency',
        'prediction_failed' => 'Failed to predict costs',
        'history_failed' => 'Failed to get usage history',
        'global_stats_failed' => 'Failed to get global statistics',
        'top_users_failed' => 'Failed to get top users',
        'report_generation_failed' => 'Failed to generate report',
        'providers_fetch_failed' => 'Failed to fetch provider information',
        'health_check_failed' => 'Health check failed',
        'provider_unavailable' => 'The specified provider is not available',
        'daily_limit_exceeded' => 'Daily usage limit exceeded',
        'monthly_limit_exceeded' => 'Monthly usage limit exceeded',
        'rate_limit_exceeded' => 'Rate limit exceeded. Please wait before making another request',
        'cost_tracking_failed' => 'Failed to record cost tracking',
    ],

    // Normalization related
    'normalization_failed' => 'Normalization process failed',
    'batch_normalization_failed' => 'Batch normalization process failed',
    'rate_limit_exceeded' => ':operation limit exceeded. Please try again in :minutes minutes',
    'brand_name' => 'Brand name',
    'fragrance_name' => 'Fragrance name',
    'provider' => 'Provider',
    'language' => 'Language',
    'fragrances' => 'Fragrances',

    // Feedback
    'feedback' => [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'acceptable' => 'Acceptable',
        'poor' => 'Poor',
        'thank_you' => 'Thank you for your feedback. We will use it to improve our service.',
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
