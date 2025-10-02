<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIFeedback extends Model
{
    use HasFactory;

    protected $table = 'ai_feedback';

    protected $fillable = [
        'user_id',
        'session_id',
        'operation_type',
        'query_type',
        'query',
        'request_params',
        'ai_provider',
        'ai_model',
        'ai_suggestions',
        'user_action',
        'selected_suggestion',
        'final_input',
        'relevance_score',
        'was_helpful',
        'user_notes',
        'user_agent',
        'ip_address',
        'context_data',
    ];

    protected $casts = [
        'request_params' => 'array',
        'ai_suggestions' => 'array',
        'selected_suggestion' => 'array',
        'context_data' => 'array',
        'was_helpful' => 'boolean',
        'relevance_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 特定のクエリに対する過去の成功パターンを取得
     */
    public static function getSuccessfulPatterns(string $query, string $operationType = 'completion', int $limit = 5): array
    {
        return self::where('operation_type', $operationType)
            ->where('query', 'LIKE', "%{$query}%")
            ->where('user_action', 'selected')
            ->where('was_helpful', true)
            ->orderByDesc('relevance_score')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($feedback) {
                return [
                    'query' => $feedback->getAttribute('query'),
                    'selected_suggestion' => $feedback->getAttribute('selected_suggestion'),
                    'relevance_score' => $feedback->getAttribute('relevance_score'),
                    'final_input' => $feedback->getAttribute('final_input'),
                ];
            })
            ->toArray();
    }

    /**
     * Few-shot学習用の例を取得
     */
    public static function getFewShotExamples(string $operationType = 'completion', int $limit = 3): array
    {
        return self::where('operation_type', $operationType)
            ->where('user_action', 'selected')
            ->where('was_helpful', true)
            ->where('relevance_score', '>=', 0.8)
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->map(function ($feedback) {
                return [
                    'query' => $feedback->getAttribute('query'),
                    'selected_text' => $feedback->getAttribute('selected_suggestion')['text'] ?? $feedback->getAttribute('final_input') ?? '',
                    'relevance_score' => $feedback->getAttribute('relevance_score'),
                    'context' => $feedback->getAttribute('request_params'),
                ];
            })
            ->toArray();
    }
}
