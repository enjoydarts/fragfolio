<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

class AIFeedbackService
{
    /**
     * AI提案の選択を記録
     */
    public function recordSelection(array $data): void
    {
        try {
            \App\Models\AIFeedback::create([
                'user_id' => $data['user_id'] ?? null,
                'session_id' => $data['session_id'] ?? Str::uuid(),
                'operation_type' => $data['operation_type'],
                'query' => $data['query'],
                'request_params' => $data['request_params'] ?? [],
                'ai_provider' => $data['ai_provider'],
                'ai_model' => $data['ai_model'],
                'ai_suggestions' => $data['ai_suggestions'],
                'user_action' => 'selected',
                'selected_suggestion' => $data['selected_suggestion'],
                'final_input' => $data['final_input'] ?? null,
                'relevance_score' => $data['relevance_score'] ?? null,
                'was_helpful' => true,
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'context_data' => $data['context_data'] ?? [],
            ]);
        } catch (\Exception $e) {
            \Log::warning('AI Feedback recording failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * AI提案の拒否を記録
     */
    public function recordRejection(array $data): void
    {
        try {
            \App\Models\AIFeedback::create([
                'user_id' => $data['user_id'] ?? null,
                'session_id' => $data['session_id'] ?? Str::uuid(),
                'operation_type' => $data['operation_type'],
                'query' => $data['query'],
                'request_params' => $data['request_params'] ?? [],
                'ai_provider' => $data['ai_provider'],
                'ai_model' => $data['ai_model'],
                'ai_suggestions' => $data['ai_suggestions'],
                'user_action' => 'rejected',
                'final_input' => $data['final_input'] ?? null,
                'was_helpful' => false,
                'user_notes' => $data['user_notes'] ?? null,
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'context_data' => $data['context_data'] ?? [],
            ]);
        } catch (\Exception $e) {
            \Log::warning('AI Feedback recording failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ユーザー修正を記録
     */
    public function recordModification(array $data): void
    {
        try {
            \App\Models\AIFeedback::create([
                'user_id' => $data['user_id'] ?? null,
                'session_id' => $data['session_id'] ?? Str::uuid(),
                'operation_type' => $data['operation_type'],
                'query' => $data['query'],
                'request_params' => $data['request_params'] ?? [],
                'ai_provider' => $data['ai_provider'],
                'ai_model' => $data['ai_model'],
                'ai_suggestions' => $data['ai_suggestions'],
                'user_action' => 'modified',
                'selected_suggestion' => $data['original_suggestion'] ?? null,
                'final_input' => $data['final_input'],
                'relevance_score' => $data['relevance_score'] ?? null,
                'was_helpful' => $data['was_helpful'] ?? null,
                'user_notes' => $data['user_notes'] ?? null,
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'context_data' => $data['context_data'] ?? [],
            ]);
        } catch (\Exception $e) {
            \Log::warning('AI Feedback recording failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * セッション用のUUIDを生成
     */
    public function generateSessionId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Few-shot学習用の成功例を取得
     */
    public function getFewShotExamples(string $query, string $operationType = 'completion', int $limit = 3): array
    {
        try {
            // Few-shot学習なので、多様な高品質な成功例を提供する
            return \App\Models\AIFeedback::where('operation_type', $operationType)
                ->where('user_action', 'selected')
                ->where('was_helpful', true)
                ->where('relevance_score', '>=', 0.8)
                ->orderByDesc('relevance_score')
                ->inRandomOrder() // 多様性を確保
                ->limit($limit)
                ->get()
                ->map(function ($feedback) {
                    return [
                        'query' => $feedback->getAttribute('query'),
                        'selected_text' => $feedback->getAttribute('selected_suggestion')['text'] ?? '',
                        'relevance_score' => $feedback->getAttribute('relevance_score') ?? 0.5,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            \Log::warning('Failed to get few-shot examples', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 一般的なFew-shot例を取得（フォールバック用）
     */
    public function getGeneralFewShotExamples(string $operationType = 'completion', int $limit = 3): array
    {
        try {
            return \App\Models\AIFeedback::where('operation_type', $operationType)
                ->where('user_action', 'selected')
                ->where('was_helpful', true)
                ->where('relevance_score', '>=', 0.8)
                ->orderByDesc('relevance_score')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(function ($feedback) {
                    return [
                        'query' => $feedback->getAttribute('query'),
                        'selected_text' => $feedback->getAttribute('selected_suggestion')['text'] ?? '',
                        'relevance_score' => $feedback->getAttribute('relevance_score') ?? 0.5,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            // 最終フォールバック：基本的な例
            return [
                [
                    'query' => 'シャネル',
                    'selected_text' => 'CHANEL ブルー ドゥ シャネル',
                    'relevance_score' => 0.95,
                ],
                [
                    'query' => 'バニラ',
                    'selected_text' => 'TOM FORD タバコ バニラ',
                    'relevance_score' => 0.90,
                ],
            ];
        }
    }
}
